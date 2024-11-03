<?php

namespace StripeIntegration\Payments\Model\Webhooks;

class MissingOrderHandler
{
    private $wasOrderPlaced = false;
    private $wasAdminNotified = false;
    private $placedOrder = null;
    private $orderHelper;
    private $quoteHelper;
    private $convert;
    private $config;
    private $quoteManagement;
    private $stripePaymentIntentsCollectionFactory;
    private $stripePaymentIntentModel;
    private $emailHelper;
    private $logger;
    private $helper;
    private $addressRenderer;
    private $orderAddressFactory;
    private $checkoutFlow;
    private $chargeSucceededEvent;

    public function __construct(
        \StripeIntegration\Payments\Helper\Order $orderHelper,
        \StripeIntegration\Payments\Helper\Quote $quoteHelper,
        \StripeIntegration\Payments\Helper\Convert $convert,
        \StripeIntegration\Payments\Helper\Email $emailHelper,
        \StripeIntegration\Payments\Helper\Logger $logger,
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Model\ResourceModel\PaymentIntent\CollectionFactory $stripePaymentIntentsCollectionFactory,
        \StripeIntegration\Payments\Model\Checkout\Flow $checkoutFlow,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Sales\Model\Order\Address\Renderer $addressRenderer,
        \Magento\Sales\Model\Order\AddressFactory $orderAddressFactory,
        \StripeIntegration\Payments\Model\Stripe\Event\ChargeSucceeded $chargeSucceededEvent
    )
    {
        $this->orderHelper = $orderHelper;
        $this->quoteHelper = $quoteHelper;
        $this->convert = $convert;
        $this->emailHelper = $emailHelper;
        $this->logger = $logger;
        $this->helper = $helper;
        $this->config = $config;
        $this->stripePaymentIntentsCollectionFactory = $stripePaymentIntentsCollectionFactory;
        $this->checkoutFlow = $checkoutFlow;
        $this->quoteManagement = $quoteManagement;
        $this->addressRenderer = $addressRenderer;
        $this->orderAddressFactory = $orderAddressFactory;
        $this->chargeSucceededEvent = $chargeSucceededEvent;
    }

    public function fromEvent(array $event)
    {
        $updatedCharge = null;
        $this->placedOrder = null;
        $this->wasOrderPlaced = false;
        $this->wasAdminNotified = false;

        if (empty($event['type']) || $event['type'] != 'charge.succeeded')
            return $this;

        if (empty($event['data']['object']['metadata']['Order #']))
            return $this;

        if ($this->isLessThanMinutesOld($event['data']['object']['created'], 10))
            return $this;

        if ($this->orderHelper->loadOrderByIncrementId($event['data']['object']['metadata']['Order #']))
            return $this;

        $charge = $event['data']['object'];
        $quote = $this->loadQuoteByOrderIncrementId($charge['metadata']['Order #'], $charge['payment_intent']);
        if (!$quote)
        {
            $this->notifyAdminQuoteIsMissing($charge);
            return $this;
        }

        if (!$this->grandTotalMatches($quote, $charge['amount']))
        {
            $this->notifyAdminGrandTotalMismatch($quote, $charge);
            return $this;
        }

        if (!$this->currencyMatches($quote, $charge['currency']))
        {
            $this->notifyAdminCurrencyMismatch($quote, $charge);
            return $this;
        }

        if ($this->quoteHelper->hasSubscriptionsWithStartDate($quote))
        {
            return $this;
        }

        try
        {
            $this->placedOrder = $order = $this->reAttemptOrderPlacement($quote, $charge);
            $updatedCharge = $this->updateChargeFromOrder($order, $charge);
            $this->processEvent($event, $updatedCharge);
            $message = __("This order failed to be created when the original payment was collected. It has been automatically re-created via it's charge.succeeded webhook event.");
            $this->orderHelper->addOrderComment($message, $order);
            $this->orderHelper->saveOrder($order);
            $this->notifyAdminOrderPlaced($order, $quote, $charge);
        }
        catch (\Exception $e)
        {
            $this->notifyAdminCouldNotPlaceOrder($e, $quote, $charge);
        }

        return $this;
    }

    private function processEvent($event, $updatedCharge)
    {
        if (!$updatedCharge)
            return;

        // Try to process the charge.succeeded event
        try
        {
            $updatedCharge = json_decode(json_encode($updatedCharge), true);
            $event['data']['object'] = $updatedCharge;
            $this->chargeSucceededEvent->process($event, $updatedCharge);
        }
        catch (\Exception $e)
        {
            $this->logger->logError($e->getMessage(), $e->getTraceAsString());
        }
    }

    public function wasOrderPlaced()
    {
        return $this->wasOrderPlaced;
    }

    public function getPlacedOrder()
    {
        return $this->placedOrder;
    }

    public function wasAdminNotified()
    {
        return $this->wasAdminNotified;
    }

    private function isLessThanMinutesOld($timestamp, $minutes)
    {
        return time() - $timestamp < $minutes * 60;
    }

    private function loadQuoteByOrderIncrementId($orderIncrementId, $paymentIntentId)
    {
        $this->stripePaymentIntentModel = $this->stripePaymentIntentsCollectionFactory->create()
            ->addFieldToFilter('order_increment_id', $orderIncrementId)
            ->getFirstItem();

        if (!$this->stripePaymentIntentModel->getQuoteId())
        {
            $this->stripePaymentIntentModel = $this->stripePaymentIntentsCollectionFactory->create()
                ->addFieldToFilter('pi_id', $paymentIntentId)
                ->getFirstItem();
        }

        if (!$this->stripePaymentIntentModel->getQuoteId())
            return null;

        return $this->quoteHelper->loadQuoteById($this->stripePaymentIntentModel->getQuoteId());
    }

    private function reAttemptOrderPlacement($quote, $charge)
    {
        $this->checkoutFlow->creatingOrderFromCharge = $charge;
        $this->config->reInitStripeFromStoreId($quote->getStoreId());

        if (!$quote->getCustomerEmail())
        {
            $quote->setCustomerEmail($this->stripePaymentIntentModel->getCustomerEmail());
        }

        // Place Order
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->quoteManagement->submit($quote);

        $this->wasOrderPlaced = true;
        return $order;
    }

    private function notifyAdminQuoteIsMissing($charge)
    {
        try
        {
            $generalName = $this->emailHelper->getName('general');
            $generalEmail = $this->emailHelper->getEmail('general');

            $templateVars = $this->getTemplateVars(null, $charge);

            $extraDetails = "We have reattempted the order placement asynchronously, however the original quote could not be found in the database.";

            $templateVars['extraDetails'] = $extraDetails;

            $sent = $this->emailHelper->send('stripe_missing_order', $generalName, $generalEmail, $generalName, $generalEmail, $templateVars);

            if (!$sent)
            {
                $this->logger->logError("Could not send email to admin about missing quote");
            }
            else
            {
                $this->wasAdminNotified = true;
            }
        }
        catch (\Exception $e)
        {
            $this->logger->logError($e->getMessage(), $e->getTraceAsString());
        }
    }

    private function getTemplateVars($quote, $charge)
    {
        if ($charge['livemode'])
            $mode = '';
        else
            $mode = 'test/';

        $paymentIntentId = $charge["payment_intent"];
        $paymentLink = "https://dashboard.stripe.com/{$mode}payments/$paymentIntentId";
        $formattedAmount = $this->helper->formatStripePrice($charge["amount"], $charge["currency"]);

        $templateVars = [
            'paymentIntentId' => $paymentIntentId,
            'paymentLink' => $paymentLink,
            'formattedAmount' => $formattedAmount
        ];

        if ($quote)
        {
            $templateVars['customerEmail'] = $quote->getCustomerEmail();

            if (!$quote->isVirtual())
            {
                $shippingAddress = $quote->getShippingAddress();
                $shippingAddress = $this->orderAddressFactory->create()->setData($shippingAddress->getData());
                $templateVars['formattedShippingAddress'] = $this->addressRenderer->format($shippingAddress, 'html');
                $templateVars['shippingMethod'] = $quote->getShippingAddress()->getShippingDescription();
            }

            $billingAddress = $quote->getBillingAddress();
            $billingAddress = $this->orderAddressFactory->create()->setData($billingAddress->getData());
            $templateVars['formattedBillingAddress'] = $this->addressRenderer->format($billingAddress, 'html');

            // Build a string which lists all quote items, configurable and customizable options
            $items = $quote->getAllItems();
            $itemsString = "";
            foreach ($items as $item)
            {
                $itemsString .= $item->getName() . " x " . $item->getQty() . "<br>";
                $itemsString .= "<br>";
            }

            $templateVars['orderItems'] = $itemsString;
        }

        return $templateVars;
    }

    private function notifyAdminCouldNotPlaceOrder($exception, $quote, $charge)
    {
        try
        {
            $generalName = $this->emailHelper->getName('general');
            $generalEmail = $this->emailHelper->getEmail('general');

            $templateVars = $this->getTemplateVars($quote, $charge);

            $extraDetails = "We have reattempted the order placement asynchronously, but it failed with the following error:";
            $errorMessage = $exception->getMessage();
            $stackTrace = $exception->getTraceAsString();

            $templateVars['extraDetails'] = $extraDetails;
            $templateVars['errorMessage'] = $errorMessage;
            $templateVars['stackTrace'] = $stackTrace;

            $sent = $this->emailHelper->send('stripe_missing_order', $generalName, $generalEmail, $generalName, $generalEmail, $templateVars);

            if (!$sent)
            {
                $this->logger->logError($exception->getMessage(), $exception->getTraceAsString());
            }
            else
            {
                $this->wasAdminNotified = true;
            }
        }
        catch (\Exception $e)
        {
            $this->logger->logError($e->getMessage(), $e->getTraceAsString());
        }
    }

    private function notifyAdminGrandTotalMismatch($quote, $charge)
    {
        try
        {
            $generalName = $this->emailHelper->getName('general');
            $generalEmail = $this->emailHelper->getEmail('general');

            $templateVars = $this->getTemplateVars($quote, $charge);

            $extraDetails = "We have reattempted the order placement asynchronously, however the grand total of the quote did not match the charge amount. The customer may have changed their cart items after the payment went through.";

            $templateVars['extraDetails'] = $extraDetails;

            $sent = $this->emailHelper->send('stripe_missing_order', $generalName, $generalEmail, $generalName, $generalEmail, $templateVars);

            if (!$sent)
            {
                $this->logger->logError("Could not send email to admin about grand total mismatch");
            }
            else
            {
                $this->wasAdminNotified = true;
            }
        }
        catch (\Exception $e)
        {
            $this->logger->logError($e->getMessage(), $e->getTraceAsString());
        }
    }

    private function notifyAdminCurrencyMismatch($quote, $charge)
    {
        try
        {
            $generalName = $this->emailHelper->getName('general');
            $generalEmail = $this->emailHelper->getEmail('general');

            $templateVars = $this->getTemplateVars($quote, $charge);

            $extraDetails = "We have reattempted the order placement asynchronously, however the currency of the quote did not match the charge currency.";

            $templateVars['extraDetails'] = $extraDetails;

            $sent = $this->emailHelper->send('stripe_missing_order', $generalName, $generalEmail, $generalName, $generalEmail, $templateVars);

            if (!$sent)
            {
                $this->logger->logError("Could not send email to admin about currency mismatch");
            }
            else
            {
                $this->wasAdminNotified = true;
            }
        }
        catch (\Exception $e)
        {
            $this->logger->logError($e->getMessage(), $e->getTraceAsString());
        }
    }

    private function notifyAdminOrderPlaced($order, $quote, $charge)
    {
        try
        {
            $generalName = $this->emailHelper->getName('general');
            $generalEmail = $this->emailHelper->getEmail('general');

            $templateVars = $this->getTemplateVars($quote, $charge);

            $extraDetails = "We have reattempted the order placement asynchronously and it was successful (#{$order->getIncrementId()}). The order has been placed and the customer has been notified.";

            $templateVars['extraDetails'] = $extraDetails;

            $sent = $this->emailHelper->send('stripe_missing_order', $generalName, $generalEmail, $generalName, $generalEmail, $templateVars);

            if (!$sent)
            {
                $this->logger->logError("Could not send email to admin about order placement");
            }
            else
            {
                $this->wasAdminNotified = true;
            }
        }
        catch (\Exception $e)
        {
            $this->logger->logError($e->getMessage(), $e->getTraceAsString());
        }
    }

    private function grandTotalMatches($quote, $chargeStripeAmount)
    {
        $quoteStripeAmount = $this->convert->magentoAmountToStripeAmount($quote->getGrandTotal(), $quote->getQuoteCurrencyCode());
        return $quoteStripeAmount == $chargeStripeAmount;
    }

    private function currencyMatches($quote, $chargeCurrency)
    {
        return $quote->getQuoteCurrencyCode() == strtoupper($chargeCurrency);
    }

    private function updateChargeFromOrder($order, $charge)
    {
        $updateParams = [
            'description' => $this->orderHelper->getOrderDescription($order),
            'metadata' => $this->config->getMetadata($order)
        ];

        try
        {
            $updatedCharge = $this->config->getStripeClient()->charges->update($charge['id'], $updateParams);
            $this->config->getStripeClient()->paymentIntents->update($charge['payment_intent'], $updateParams);
            return $updatedCharge;
        }
        catch (\Exception $e)
        {
            $this->logger->logError("Could not update charge with order details: " . $e->getMessage());
        }

        return null;
    }
}