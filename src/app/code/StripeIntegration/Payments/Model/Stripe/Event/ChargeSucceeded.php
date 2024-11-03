<?php

namespace StripeIntegration\Payments\Model\Stripe\Event;

use StripeIntegration\Payments\Exception\WebhookException;
use StripeIntegration\Payments\Model\Stripe\StripeObjectTrait;

class ChargeSucceeded
{
    use StripeObjectTrait;

    private $paymentIntentFactory;
    private $paymentMethodHelper;
    private $creditmemoHelper;
    private $webhooksHelper;
    private $subscriptionsHelper;
    private $dataHelper;
    private $config;
    private $helper;
    private $orderHelper;
    private $quoteHelper;
    private $multishippingHelper;
    private $json;

    public function __construct(
        \StripeIntegration\Payments\Model\Stripe\Service\StripeObjectServicePool $stripeObjectServicePool,
        \StripeIntegration\Payments\Helper\Webhooks $webhooksHelper,
        \StripeIntegration\Payments\Model\PaymentIntentFactory $paymentIntentFactory,
        \StripeIntegration\Payments\Helper\Creditmemo $creditmemoHelper,
        \StripeIntegration\Payments\Helper\PaymentMethod $paymentMethodHelper,
        \StripeIntegration\Payments\Helper\Subscriptions $subscriptionsHelper,
        \StripeIntegration\Payments\Helper\Data $dataHelper,
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Helper\Order $orderHelper,
        \StripeIntegration\Payments\Helper\Quote $quoteHelper,
        \StripeIntegration\Payments\Helper\Multishipping $multishippingHelper,
        \Magento\Framework\Serialize\Serializer\Json $json
    )
    {
        $stripeObjectService = $stripeObjectServicePool->getStripeObjectService('events');
        $this->setData($stripeObjectService);

        $this->webhooksHelper = $webhooksHelper;
        $this->paymentIntentFactory = $paymentIntentFactory;
        $this->paymentMethodHelper = $paymentMethodHelper;
        $this->creditmemoHelper = $creditmemoHelper;
        $this->subscriptionsHelper = $subscriptionsHelper;
        $this->dataHelper = $dataHelper;
        $this->config = $config;
        $this->helper = $helper;
        $this->orderHelper = $orderHelper;
        $this->quoteHelper = $quoteHelper;
        $this->multishippingHelper = $multishippingHelper;
        $this->json = $json;
    }

    public function process($arrEvent, $object)
    {
        if (!empty($object['metadata']['Multishipping']))
        {
            $orders = $this->webhooksHelper->loadOrderFromEvent($arrEvent, true);
            $paymentIntentModel = $this->paymentIntentFactory->create();

            foreach ($orders as $order)
            {
                $successfulOrders = $this->multishippingHelper->getSuccessfulOrdersForQuoteId($order->getQuoteId());
                $this->onMultishippingChargeSucceeded($successfulOrders, $order->getQuoteId());
                break;
            }

            return;
        }

        if ($this->webhooksHelper->wasCapturedFromAdmin($object))
            return;

        $order = $this->webhooksHelper->loadOrderFromEvent($arrEvent);
        $hasSubscriptions = $this->orderHelper->hasSubscriptionsIn($order->getAllItems());

        // Set Stripe payment method
        $this->orderHelper->setRiskDataFrom($object, $order);
        $this->insertPaymentMethods($object, $order);

        $stripeInvoice = null;
        if (!empty($object['invoice']))
        {
            $stripeInvoice = $this->config->getStripeClient()->invoices->retrieve($object['invoice'], []);
            if ($stripeInvoice->billing_reason == "subscription_cycle" // A subscription has renewed
                || $stripeInvoice->billing_reason == "subscription_update" // A trial subscription was manually ended
                || $stripeInvoice->billing_reason == "subscription_threshold" // A billing threshold was reached
            )
            {
                // We may receive a charge.succeeded event from a recurring subscription payment. In that case we want to create
                // a new order for the new payment, rather than registering the charge against the original order.
                return;
            }
        }

        if (!$order->getEmailSent())
        {
            $wasTransactionPending = $order->getPayment()->getAdditionalInformation("is_transaction_pending");

            if ($wasTransactionPending)
            {
                $this->orderHelper->sendNewOrderEmailFor($order);
            }
        }

        if (empty($object['payment_intent']))
            throw new WebhookException("This charge was not created by a payment intent.");

        $transactionId = $object['payment_intent'];

        $payment = $order->getPayment();
        $payment->setTransactionId($transactionId)
            ->setLastTransId($transactionId)
            ->setIsTransactionPending(false)
            ->setAdditionalInformation("is_transaction_pending", false) // this is persisted
            ->setIsTransactionClosed(0)
            ->setIsFraudDetected(false)
            ->save();

        $amountCaptured = ($object["captured"] ? $object['amount_captured'] : 0);

        $this->onTransaction($order, $object, $transactionId);

        $paymentIntent = $this->config->getStripeClient()->paymentIntents->retrieve($transactionId, []);
        if (empty($paymentIntent->metadata->{"Order #"}))
        {
            $this->config->getStripeClient()->paymentIntents->update($object['payment_intent'], [
                'metadata' => $this->config->getMetadata($order),
                'description' => $this->orderHelper->getOrderDescription($order)
            ]);
        }

        if ($amountCaptured > 0)
        {
            $this->helper->invoiceOrder($order, $transactionId, \Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE, true);
        }
        else if ($amountCaptured == 0) // Authorize Only mode
        {
            if ($hasSubscriptions)
            {
                // If it has trial subscriptions, we want a Paid invoice which will partially refund
                $this->helper->invoiceOrder($order, $transactionId, \Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE, true);
            }
        }

        if ($this->config->isStripeRadarEnabled() && !empty($object['outcome']['type']) && $object['outcome']['type'] == "manual_review")
            $this->orderHelper->holdOrder($order);

        $order = $this->orderHelper->saveOrder($order);

        // Update the payment intents table, because the payment method was created after the order was placed
        $paymentIntentModel = $this->paymentIntentFactory->create()->load($object['payment_intent'], 'pi_id');
        $quoteId = $paymentIntentModel->getQuoteId();
        if ($quoteId == $order->getQuoteId())
        {
            $paymentIntentModel->setPmId($object['payment_method']);
            $paymentIntentModel->setOrderId($order->getId());
            if (is_numeric($order->getCustomerId()) && $order->getCustomerId() > 0)
                $paymentIntentModel->setCustomerId($order->getCustomerId());
            $paymentIntentModel->save();
        }

    }

    public function onMultishippingChargeSucceeded($successfulOrders, $quoteId)
    {
        $this->multishippingHelper->onPaymentConfirmed($quoteId, $successfulOrders);

        foreach ($successfulOrders as $order)
        {
            $this->orderHelper->sendNewOrderEmailFor($order);
        }
    }

    public function onTransaction($order, $object, $transactionId)
    {
        $action = __("Collected");
        if ($object["captured"] == false)
        {
            if ($order->getState() != "pending" && $order->getPayment()->getAdditionalInformation("server_side_transaction_id") == $transactionId)
            {
                // This transaction does not need to be recorded, it was already created when the order was placed.
                return;
            }
            $action = __("Authorized");
            $transactionType = \Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH;
            $transactionAmount = $this->helper->convertStripeAmountToOrderAmount($object['amount'], $object['currency'], $order);
        }
        else
        {
            if ($order->getTotalPaid() >= $order->getGrandTotal() && $order->getPayment()->getAdditionalInformation("server_side_transaction_id") == $transactionId)
            {
                // This transaction does not need to be recorded, it was already created when the order was placed.
                return;
            }
            $action = __("Captured");
            $transactionType = \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE;
            $transactionAmount = $this->helper->convertStripeAmountToOrderAmount($object['amount_captured'], $object['currency'], $order);
        }

        $transaction = $order->getPayment()->addTransaction($transactionType, null, false);
        $transaction->setAdditionalInformation("amount", (string)$transactionAmount);
        $transaction->setAdditionalInformation("currency", $object['currency']);
        $transaction->save();

        $state = \Magento\Sales\Model\Order::STATE_PROCESSING;
        $status = $order->getConfig()->getStateDefaultStatus($state);
        $humanReadableAmount = $this->helper->addCurrencySymbol($transactionAmount, $object['currency']);
        $comment = __("%1 amount of %2 via Stripe. Transaction ID: %3", $action, $humanReadableAmount, $transactionId);
        $order->setState($state)->addStatusToHistory($status, $comment, $isCustomerNotified = false);
    }

    private function insertPaymentMethods($paymentIntentResponse, $order)
    {
        $paymentMethodType = '';
        $cardData = [];
        if (isset($paymentIntentResponse['payment_method_details']['type'])
            && $paymentIntentResponse['payment_method_details']['type']) {
            $paymentMethod = $paymentIntentResponse['payment_method_details'];

            if ($paymentMethod['type'] === 'card') {
                $cardData = ['card_type' => $paymentMethod['card']['brand'], 'card_data' => $paymentMethod['card']['last4']];

                if (isset($paymentMethod['card']['wallet']['type']) && $paymentMethod['card']['wallet']['type']) {
                    $cardData['wallet'] = $paymentMethod['card']['wallet']['type'];
                }
            }
            $paymentMethodType = $paymentMethod['type'];
        }

        if ($paymentMethodType) {
            $this->paymentMethodHelper->savePaymentMethod($order, $paymentMethodType, $cardData);
        }
    }
}