<?php

namespace StripeIntegration\Payments\Model;

use StripeIntegration\Payments\Exception\GenericException;
use StripeIntegration\Payments\Exception\OrderPlacedAndPaidException;

class PaymentElement extends \Magento\Framework\Model\AbstractModel
{
    private $paymentIntent = null;
    private $setupIntent = null;
    private $subscription = null;
    private $subscriptionsHelper;
    private $cache;
    private $paymentIntentHelper;
    private $customer;
    private $compare;
    private $paymentIntentModelFactory;
    private $dataHelper;
    private $helper;
    private $config;
    private $stripePaymentIntent;
    private $resourceModel;
    private $orderHelper;
    private $paymentIntentCollection;
    private $quoteHelper;
    private $stripePaymentMethodFactory;
    private $setupIntentCollection;
    private $checkoutFlow;
    private $orderValidator;
    private $errorHelper;
    private $stripePaymentMethod;

    public function __construct(
        \StripeIntegration\Payments\Helper\Data $dataHelper,
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Helper\Quote $quoteHelper,
        \StripeIntegration\Payments\Helper\Compare $compare,
        \StripeIntegration\Payments\Helper\Subscriptions $subscriptionsHelper,
        \StripeIntegration\Payments\Helper\PaymentIntent $paymentIntentHelper,
        \StripeIntegration\Payments\Helper\Order $orderHelper,
        \StripeIntegration\Payments\Helper\OrderValidator $orderValidator,
        \StripeIntegration\Payments\Helper\Error $errorHelper,
        \StripeIntegration\Payments\Model\PaymentIntentFactory $paymentIntentModelFactory,
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Model\Checkout\Flow $checkoutFlow,
        \StripeIntegration\Payments\Model\Stripe\PaymentMethod $stripePaymentMethod,
        \StripeIntegration\Payments\Model\Stripe\PaymentIntent $stripePaymentIntent,
        \StripeIntegration\Payments\Model\Stripe\PaymentMethodFactory $stripePaymentMethodFactory,
        \StripeIntegration\Payments\Model\ResourceModel\PaymentElement $resourceModel,
        \StripeIntegration\Payments\Model\ResourceModel\PaymentIntent\Collection $paymentIntentCollection,
        \StripeIntegration\Payments\Model\ResourceModel\SetupIntent\Collection $setupIntentCollection,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
        )
    {
        $this->dataHelper = $dataHelper;
        $this->helper = $helper;
        $this->quoteHelper = $quoteHelper;
        $this->compare = $compare;
        $this->paymentIntentModelFactory = $paymentIntentModelFactory;
        $this->subscriptionsHelper = $subscriptionsHelper;
        $this->cache = $context->getCacheManager();
        $this->config = $config;
        $this->stripePaymentIntent = $stripePaymentIntent;
        $this->stripePaymentMethodFactory = $stripePaymentMethodFactory;
        $this->customer = $helper->getCustomerModel();
        $this->paymentIntentHelper = $paymentIntentHelper;
        $this->resourceModel = $resourceModel;
        $this->paymentIntentCollection = $paymentIntentCollection;
        $this->setupIntentCollection = $setupIntentCollection;
        $this->orderValidator = $orderValidator;
        $this->orderHelper = $orderHelper;
        $this->errorHelper = $errorHelper;
        $this->checkoutFlow = $checkoutFlow;
        $this->stripePaymentMethod = $stripePaymentMethod;

        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    protected function _construct()
    {
        $this->_init('StripeIntegration\Payments\Model\ResourceModel\PaymentElement');
    }

    public function updateFromOrder($order)
    {
        try
        {
            if (!$this->helper->isMultiShipping())
            {
                $this->orderValidator->validate($order);
            }
        }
        catch (OrderPlacedAndPaidException $e)
        {
            $quote = $this->quoteHelper->loadQuoteById($order->getQuoteId());
            $quote->setIsActive(false);
            $this->quoteHelper->saveQuote($quote);
            return $this->helper->throwError(__("The order has already been placed and paid."));
        }

        $quote = $this->quoteHelper->loadQuoteById($order->getQuoteId());

        $this->resourceModel->load($this, $quote->getId(), 'quote_id');

        if ($this->getOrderIncrementId() && $this->getOrderIncrementId() != $order->getIncrementId())
        {
            // Check if this is a duplicate order placement. The old order should have normally been canceled if the cart changed.
            $oldOrder = $this->orderHelper->loadOrderByIncrementId($this->getOrderIncrementId());
            if ($oldOrder && $oldOrder->getState() != "canceled" && !$this->helper->isMultiShipping() && $this->orderHelper->orderAgeLessThan(120, $oldOrder))
            {
                // The case where the old order was not canceled is when the payment failed and the cart contents changed
                $this->setOrderIncrementId($order->getIncrementId())->save();
                if ($order->getGrandTotal() == $oldOrder->getGrandTotal() && $order->getOrderCurrencyCode() == $oldOrder->getOrderCurrencyCode())
                {
                    $comment = __("The customer details have changed, a different checkout flow was selected, or a checkout error occurred. The order is canceled because a new one will be placed (#%1) with the new details.", $order->getIncrementId());
                }
                else
                {
                    $comment = __("The cart contents have changed. The order is canceled because a new one will be placed (#%1) with the new details.", $order->getIncrementId());
                }

                $oldOrder->addStatusToHistory($status = false, $comment, $isCustomerNotified = false);
                $this->orderHelper->removeTransactions($oldOrder);
                $this->helper->cancelOrCloseOrder($oldOrder, true);
            }
        }

        // In the following special checkout flow, which requires microdeposits verification, we only set up a payment
        // method and use it later via webhook observers to set up the subscription
        $setupIntentModel = $this->setupPaymentMethod($order);
        if ($setupIntentModel)
        {
            $setupIntent = $this->setupIntent = $setupIntentModel->getStripeObject();
            $this->setSetupIntentId($setupIntent->id);
            $this->setPaymentIntentId(null);
            $this->paymentIntent = null;
            $this->setSubscriptionId(null);
            $this->subscription = null;
            $this->setOrderIncrementId($order->getIncrementId());
            $this->setQuoteId($order->getQuoteId());
            $this->resourceModel->save($this);
            $this->checkoutFlow->isFutureSubscriptionSetup = true;
            $this->checkoutFlow->isPendingMicrodepositsVerification = true;
            return;
        }

        // Update any existing subscriptions
        $paymentIntentModel = $this->paymentIntentModelFactory->create();
        $params = $paymentIntentModel->getParamsFrom($order);

        if (!empty($params['payment_method']))
        {
            $this->validatePaymentMethod($params['payment_method']);
        }

        $subscription = $this->subscriptionsHelper->updateSubscriptionFromOrder($order, $this->getSubscriptionId(), $params);

        if (!empty($subscription->id))
        {
            $this->updateFromSubscription($subscription);
            $order->getPayment()->setAdditionalInformation("subscription_id", $subscription->id);
            $this->subscription = $subscription;
        }

        $paymentIntent = $setupIntent = null;

        if (!empty($subscription->latest_invoice->payment_intent->id))
        {
            $paymentIntent = $subscription->latest_invoice->payment_intent;
        }
        else if (!empty($subscription->pending_setup_intent->id))
        {
            $setupIntent = $subscription->pending_setup_intent;
        }
        else if (!empty($subscription))
        {
            // Case for subscriptions with start dates. Do not create a payment intent.
        }
        else
        {
            // Update any existing payment intents
            $paymentIntentModel = $this->paymentIntentCollection->findByQuoteId($quote->getId());
            if (!$paymentIntentModel)
                $paymentIntentModel = $this->paymentIntentModelFactory->create();

            $paymentIntent = $paymentIntentModel->createPaymentIntentFrom($params, $quote, $order);
        }

        // Upon order placement, a customer is always created in Stripe
        if ($this->customer->getStripeId())
        {
            $this->customer->updateFromOrder($order);
            $params['customer'] = $this->customer->getStripeId();
        }

        if ($paymentIntent)
        {
            $this->setupIntent = null;
            $this->setSetupIntentId(null);
            $this->setPaymentIntentId($paymentIntent->id);

            $this->paymentIntent = $this->updatePaymentIntentFrom($paymentIntent, $params);
        }
        else if ($setupIntent)
        {
            $this->paymentIntent = null;
            $this->setSetupIntentId($setupIntent->id);
            $this->setPaymentIntentId(null);

            $this->setupIntent = $this->updateSetupIntentFrom($setupIntent, $params);
        }

        if (!empty($subscription))
        {
            $this->subscription = $subscription;
            $this->setSubscriptionId($subscription->id);
        }

        $this->setOrderIncrementId($order->getIncrementId());
        $this->setQuoteId($order->getQuoteId());
        $this->resourceModel->save($this);
    }

    public function validatePaymentMethod($paymentMethodId)
    {
        $paymentMethod = $this->stripePaymentMethod->fromPaymentMethodId($paymentMethodId)->getStripeObject();
        if (!empty($paymentMethod->customer) && $this->customer->getStripeId() && $paymentMethod->customer != $this->customer->getStripeId())
        {
            $this->helper->throwError(__("This payment method cannot be used."));
        }
    }

    // This method checks if any subscriptions with start dates are in the cart,
    // and if so, tries to set up a saved payment method to be used later for the
    // subscription creation when the setup intent eventually succeeds.
    // Returns a confirmed SetupIntent model only if it requires microdeposits verification
    public function setupPaymentMethod($order): ?\StripeIntegration\Payments\Model\SetupIntent
    {
        if (!$this->quoteHelper->hasSubscriptionsWithStartDate())
            return null;

        if ($order->getPayment()->getAdditionalInformation("confirmation_token"))
        {
            // Confirmation tokens are only supported by the Express Checkout Element,
            // which does not support payment methods such as ACH, and therefore
            // the payment method will not need any verification by the customer.
            return null;
        }

        $paymentMethodId = $order->getPayment()->getAdditionalInformation("token");
        $paymentMethod = $this->stripePaymentMethodFactory->create()->fromPaymentMethodId($paymentMethodId)->getStripeObject();

        if ($paymentMethod->type != "us_bank_account")
        {
            // We currently only deal with ACH Direct Debit
            return null;
        }

        $setupIntentModel = $this->setupIntentCollection->findByQuoteId($order->getQuoteId());
        $setupIntentModel->initFromOrder($order);

        if ($setupIntentModel->requiresMicrodepositsVerification())
        {
            $setupIntentModel->setIsDelayedSubscriptionSetup(true);
            return $setupIntentModel->save();
        }
        else if ($setupIntentModel->getSiId())
        {
            $setupIntentModel->cancel();
            $setupIntentModel->delete();
        }

        return null;
    }

    // There are some cases where an error occurred after placing an order, and somehow the quote was
    // recreated, thus losing the reference to the old Pending order. In those cases, we want to search
    // and find those pending orders and manually cancel them before creating a new one using the same
    // payment intent ID. Having 2 orders with the same payment intent ID is very problematic.
    public function cancelInvalidOrders($currentOrder)
    {
        $transactionId = $this->getPaymentIntentId();

        if (!$transactionId || $this->helper->isMultiShipping())
        {
            return;
        }

        $orders = $this->helper->getOrdersByTransactionId($transactionId);

        $comment = __("The cart contents or customer details have changed, or a checkout crash occurred. The order is canceled because a new one will be placed (#%1).", $currentOrder->getIncrementId());

        foreach ($orders as $order)
        {
            if ($order->getIncrementId() == $currentOrder->getIncrementId())
            {
                continue;
            }

            if ($order->getState() == "canceled")
            {
                continue;
            }

            try
            {
                $quote = $this->quoteHelper->loadQuoteById($order->getQuoteId());
                if ($this->helper->isMultiShipping($quote))
                {
                    continue;
                }
                $order->addStatusToHistory($status = false, $comment, $isCustomerNotified = false);
                $this->orderHelper->removeTransactions($order);
                $this->helper->cancelOrCloseOrder($order, true);

                if ($currentOrder->getQuoteId() != $order->getQuoteId())
                {
                    $this->paymentIntentCollection->deleteForQuoteId($order->getQuoteId());
                }
            }
            catch (\Exception $e)
            {
                $this->helper->logError("Could not cancel invalid order: " . $e->getMessage());
            }
        }
    }

    public function updatePaymentIntentFrom($paymentIntent, $params)
    {
        $updateParams = $this->getFilteredParamsForUpdate($paymentIntent, $params);

        if ($this->compare->isDifferent($paymentIntent, $updateParams))
            return $this->config->getStripeClient()->paymentIntents->update($paymentIntent->id, $updateParams);

        return $paymentIntent;
    }

    public function updateSetupIntentFrom($setupIntent, $params)
    {
        $updateParams = $this->getFilteredParamsForUpdate($setupIntent, $params);

        if ($this->compare->isDifferent($setupIntent, $updateParams))
            return $this->config->getStripeClient()->setupIntents->update($setupIntent->id, $updateParams);

        return $setupIntent;
    }

    protected function getFilteredParamsForUpdate($object, $params)
    {
        $updateParams = $this->paymentIntentHelper->getFilteredParamsForUpdate($params, $object);

        if ($this->getSetupIntent() || $this->getSubscription())
        {
            unset($updateParams['amount']); // If we have a subscription, the amount will be incorrect here: Order total - Subscriptions total
        }

        return ($updateParams ? $updateParams : []);
    }

    public function getSavedPaymentMethods($quoteId = null)
    {
        $customer = $this->helper->getCustomerModel();

        if (!$customer->getStripeId() || !$this->helper->isCustomerLoggedIn())
            return [];

        $quote = $this->quoteHelper->getQuote($quoteId);
        if (!$quote)
            return [];

        if (!$quoteId)
            $quoteId = $quote->getId();

        $savedMethods = $customer->getSavedPaymentMethods(null, true);

        return $savedMethods;
    }


    public function isOrderPlaced()
    {
        if (!$this->getOrderIncrementId())
        {
            $quote = $this->quoteHelper->getQuote();

            if (!$quote || !$quote->getId())
            {
                return false;
            }

            $this->resourceModel->load($this, $quote->getId(), 'quote_id');
        }

        return (bool)($this->getOrderIncrementId());
    }

    public function getSubscription(): ?\Stripe\Subscription
    {
        return $this->subscription;
    }

    public function getPaymentIntent(): ?\Stripe\PaymentIntent
    {
        return $this->paymentIntent;
    }

    public function getSetupIntent(): ?\Stripe\SetupIntent
    {
        return $this->setupIntent;
    }

    public function fromQuoteId($quoteId)
    {
        $this->resourceModel->load($this, $quoteId, "quote_id");

        if ($this->getPaymentIntentId())
        {
            try
            {
                $paymentIntent = $this->stripePaymentIntent->fromPaymentIntentId($this->getPaymentIntentId())->getStripeObject();
                $this->paymentIntent = $paymentIntent;
            }
            catch (\Stripe\Exception\InvalidRequestException $e)
            {
                if ($e->getHttpStatus() == 404)
                {
                    $this->paymentIntent = null;
                    $this->setPaymentIntentId(null)->save();
                }
                else
                {
                    throw $e;
                }
            }
        }

        if ($this->getSetupIntentId())
        {
            try
            {
                $this->setupIntent = $this->config->getStripeClient()->setupIntents->retrieve($this->getSetupIntentId(), []);
            }
            catch (\Stripe\Exception\InvalidRequestException $e)
            {
                if ($e->getHttpStatus() == 404)
                {
                    $this->paymentIntent = null;
                    $this->setSetupIntentId(null)->save();
                }
                else
                {
                    throw $e;
                }
            }
        }

        if ($this->getSubscriptionId())
        {
            try
            {
                $this->subscription = $this->config->getStripeClient()->subscriptions->retrieve($this->getSubscriptionId(), []);
            }
            catch (\Stripe\Exception\InvalidRequestException $e)
            {
                if ($e->getHttpStatus() == 404)
                {
                    $this->paymentIntent = null;
                    $this->setSubscriptionId(null)->save();
                }
                else
                {
                    throw $e;
                }
            }
        }

        return $this;
    }

    public function isTrialSubscription()
    {
        if ($this->getPaymentIntentId() || $this->getSetupIntentId() || !$this->getSubscription())
        {
            return false;
        }

        return ($this->getSubscription()->status == "trialing");
    }

    public function confirm($order)
    {
        if (!$this->getQuoteId())
        {
            throw new GenericException("Not initialized");
        }

        if ($confirmationObject = $this->getPaymentIntent())
        {
            if (empty($confirmationObject->metadata->{'Order #'}))
            {
                $confirmationObject = $this->paymentIntent = $this->config->getStripeClient()->paymentIntents->update($confirmationObject->id, [
                    'description' => $this->orderHelper->getOrderDescription($order),
                    'metadata' => $this->config->getMetadata($order)
                ]);
            }

            // Wallet button 3DS confirms the PI on the client side and retries order placement
            if ($this->paymentIntentHelper->isSuccessful($confirmationObject) ||
                $this->paymentIntentHelper->isAsyncProcessing($confirmationObject) ||
                $this->paymentIntentHelper->requiresOfflineAction($confirmationObject))
            {
                // We get here in 2 cases:
                // a) A checkout crash in a sales_order_place_after observer may have forced the customer to place the order twice
                // b) Non-PaymentElement 3D Secure authentications or handleNextActions, which were done on the client side, i.e. GraphQL, Wallet button etc
                return $confirmationObject;
            }

            $confirmParams = $this->paymentIntentHelper->getConfirmParams($order, $confirmationObject, true);

            try
            {
                $result = $this->config->getStripeClient()->paymentIntents->confirm($confirmationObject->id, $confirmParams);
                $this->paymentIntent = $result;
            }
            catch (\Stripe\Exception\InvalidRequestException $e)
            {
                if (!$this->errorHelper->isMOTOError($e->getError()))
                    throw $e;

                $this->cache->save($value = "1", $key = "no_moto_gate", ["stripe_payments"], $lifetime = 6 * 60 * 60);
                unset($confirmParams['payment_method_options']['card']['moto']);
                $result = $this->config->getStripeClient()->paymentIntents->confirm($confirmationObject->id, $confirmParams);
                $this->paymentIntent = $result;
            }
        }
        else if ($confirmationObject = $this->getSetupIntent())
        {
            // We get in here when 3DS is required for trial subscriptions or subscriptions with start dates
            if (empty($confirmationObject->metadata->{'Order #'}))
            {
                $confirmationObject = $this->setupIntent = $this->config->getStripeClient()->setupIntents->update($confirmationObject->id, [
                    'description' => $this->orderHelper->getOrderDescription($order),
                    'metadata' => $this->config->getMetadata($order)
                ]);
            }

            // Wallet button 3DS confirms the SI on the client side and retries order placement
            if ($confirmationObject->status == "succeeded")
            {
                return $confirmationObject;
            }

            $confirmParams = $this->paymentIntentHelper->getConfirmParams($order, $confirmationObject, true);
            $confirmParams = $this->dataHelper->convertToSetupIntentConfirmParams($confirmParams);

            try
            {
                $result = $this->config->getStripeClient()->setupIntents->confirm($confirmationObject->id, $confirmParams);
                $this->setupIntent = $result;
            }
            catch (\Stripe\Exception\InvalidRequestException $e)
            {
                if (!$this->errorHelper->isMOTOError($e->getError()))
                    throw $e;

                $this->cache->save($value = "1", $key = "no_moto_gate", ["stripe_payments"], $lifetime = 6 * 60 * 60);
                unset($confirmParams['payment_method_options']['card']['moto']);
                $result = $this->config->getStripeClient()->setupIntents->confirm($confirmationObject->id, $confirmParams);
                $this->setupIntent = $result;
            }
        }
        else if ($confirmationObject = $this->getSubscription())
        {
            // We get here in the following scenarios:
            // - Buying a subscription which has a start date, and not payment is required today
            // - Buying a subscription with ACH Direct Debit, which requires a bank account microdeposit verification
            // - A subscription is being re-activated, but it no longer has a PM, so one is collected via the checkout page
            $confirmationObject = $this->updateSubscriptionFromOrder($confirmationObject, $order);

            if ($confirmationObject->status == "trialing")
            {
                // Case where the customer is buying a trial subscription with a saved payment method
                // that has already been 3DS authenticated in a previous subscription order.
                return $confirmationObject;
            }
            else if ($confirmationObject->status == "active")
            {
                /** @var \Stripe\Subscription $confirmationObject */
                if (!empty($confirmationObject->latest_invoice->amount_due) &&
                    $confirmationObject->latest_invoice->amount_due > 0 &&
                    $confirmationObject->latest_invoice->amount_paid == 0 &&
                    !empty($confirmationObject->default_payment_method))
                {
                    // A subscription is set up with a future start date, and payment is required today.
                    if (empty($confirmationObject->latest_invoice->payment_intent))
                    {
                        try
                        {
                            $invoice = $this->config->getStripeClient()->invoices->pay($confirmationObject->latest_invoice->id, [
                                'expand' => ['payment_intent']
                            ]);
                            return $invoice->payment_intent;
                        }
                        catch (\Exception $e)
                        {
                            // 3DS might be required
                            /** @var \Stripe\Invoice $invoice */
                            $invoice = $this->config->getStripeClient()->invoices->retrieve($confirmationObject->latest_invoice->id, [
                                'expand' => ['payment_intent']
                            ]);
                            if (!empty($invoice->payment_intent->id) && $invoice->payment_intent->status == "requires_action")
                            {
                                $this->paymentIntent = $invoice->payment_intent;
                                return $this->confirm($order);
                            }
                            else
                            {
                                throw $e;
                            }
                        }
                    }
                    else
                    {
                        if (is_string($confirmationObject->latest_invoice->payment_intent))
                        {
                            $this->paymentIntent = $this->config->getStripeClient()->paymentIntents->retrieve($confirmationObject->latest_invoice->payment_intent);
                        }
                        else
                        {
                            $this->paymentIntent = $confirmationObject->latest_invoice->payment_intent;
                        }
                        return $this->confirm($order);
                    }
                }
                else
                {
                    // A subscription could be in incomplete status if user actions are required, i.e. verification of microdeposits with ACH.
                    // Subscription re-activations with a billing_cycle_anchor will also hit here.
                    return $confirmationObject;
                }
            }
            else
            {
                throw new GenericException("Could not set up subscription.");
            }
        }
        else
        {
            throw new GenericException("Could not confirm payment.");
        }

        return $result;
    }

    private function updateSubscriptionFromOrder(\Stripe\Subscription $subscription, $order): \Stripe\Subscription
    {
        $updateParams = [];

        if (empty($subscription->metadata->{'Order #'}))
        {
            $updateParams = [
                'description' => $this->orderHelper->getOrderDescription($order),
                'metadata' => $this->config->getMetadata($order)
            ];
        }

        if (($subscription->status == "trialing" || $subscription->status == "active") &&
            empty($subscription->default_payment_method))
        {
            $paymentMethodId = $this->orderHelper->getPaymentMethodId($order);
            if ($paymentMethodId)
            {
                try
                {
                    // Attach the payment method to the customer
                    $this->config->getStripeClient()->paymentMethods->attach($paymentMethodId, [
                        'customer' => $this->customer->getStripeId()
                    ]);

                    $updateParams['default_payment_method'] = $paymentMethodId;
                }
                catch (\Exception $e)
                {
                    $this->helper->logError("Could attach payment method to customer: " . $e->getMessage());
                }
            }
        }

        if (!empty($updateParams))
        {
            $subscription = $this->subscription = $this->config->getStripeClient()->subscriptions->update($subscription->id, $updateParams);
        }

        return $subscription;
    }

    public function requiresConfirmation()
    {
        if (!$this->paymentIntent && !$this->setupIntent)
        {
            if ($this->getPaymentIntentId())
            {
                $this->paymentIntent = $this->config->getStripeClient()->paymentIntents->retrieve($this->getPaymentIntentId(), []);
            }
            else if ($this->getSetupIntentId())
            {
                $this->setupIntent = $this->config->getStripeClient()->setupIntents->retrieve($this->getSetupIntentId(), []);
            }
        }

        if ($this->paymentIntent && $this->paymentIntent->status == "requires_confirmation")
        {
            return true;
        }

        if ($this->setupIntent && $this->setupIntent->status == "requires_confirmation")
        {
            return true;
        }

        return false;
    }

    public function hasPaymentMethodChanged()
    {

        if (!$this->paymentIntent && !$this->setupIntent)
        {
            if ($this->getPaymentIntentId())
            {
                $obj = $this->paymentIntent = $this->config->getStripeClient()->paymentIntents->retrieve($this->getPaymentIntentId(), []);
            }
            else if ($this->getSetupIntentId())
            {
                $obj = $this->setupIntent = $this->config->getStripeClient()->setupIntents->retrieve($this->getSetupIntentId(), []);
            }
            else
            {
                return false;
            }
        }

        if (!$this->getOrderIncrementId())
        {
            return false;
        }

        $order = $this->orderHelper->loadOrderByIncrementId($this->getOrderIncrementId());
        if (!$order)
        {
            return false;
        }

        $paymentMethodId = $order->getPayment()->getAdditionalInformation("token");
        if (empty($paymentMethodId))
        {
            return false;
        }

        if ($this->paymentIntent)
        {
            if (empty($this->paymentIntent->payment_method) || $this->paymentIntent->payment_method != $paymentMethodId)
            {
                return true;
            }
        }

        if ($this->setupIntent)
        {
            if (empty($this->setupIntent->payment_method) || $this->setupIntent->payment_method != $paymentMethodId)
            {
                return true;
            }
        }

        return false;
    }

    protected function updateFromSubscription(?\Stripe\Subscription $subscription)
    {
        if (empty($subscription->id))
            return;

        $this->setSubscriptionId($subscription->id);

        if (!empty($subscription->latest_invoice->payment_intent->id))
        {
            $this->setSetupIntentId(null);
            $this->setPaymentIntentId($subscription->latest_invoice->payment_intent->id);
            $this->setupIntent = null;
            $this->paymentIntent = $subscription->latest_invoice->payment_intent;
        }
        else if (!empty($subscription->pending_setup_intent->id))
        {
            $this->setSetupIntentId($subscription->pending_setup_intent->id);
            $this->setPaymentIntentId(null);
            $this->setupIntent = $subscription->pending_setup_intent;
            $this->paymentIntent = null;
        }

        $this->resourceModel->save($this);
    }
}
