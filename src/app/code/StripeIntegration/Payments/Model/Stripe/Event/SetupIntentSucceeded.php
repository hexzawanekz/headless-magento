<?php

namespace StripeIntegration\Payments\Model\Stripe\Event;

use StripeIntegration\Payments\Exception\GenericException;
use StripeIntegration\Payments\Exception\MissingOrderException;
use StripeIntegration\Payments\Model\Stripe\StripeObjectTrait;

class SetupIntentSucceeded
{
    use StripeObjectTrait;

    private $paymentElementFactory;
    private $webhooksHelper;
    private $config;
    private $setupIntentCollection;
    private $quoteHelper;
    private $paymentIntentModel;
    private $orderHelper;
    private $subscriptionsHelper;
    private $helper;
    private $customerModel;
    private $paymentIntentHelper;

    public function __construct(
        \StripeIntegration\Payments\Model\Stripe\Service\StripeObjectServicePool $stripeObjectServicePool,
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Model\PaymentElementFactory $paymentElementFactory,
        \StripeIntegration\Payments\Model\PaymentIntent $paymentIntentModel,
        \StripeIntegration\Payments\Model\ResourceModel\SetupIntent\Collection $setupIntentCollection,
        \StripeIntegration\Payments\Helper\Webhooks $webhooksHelper,
        \StripeIntegration\Payments\Helper\Subscriptions $subscriptionsHelper,
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Helper\Quote $quoteHelper,
        \StripeIntegration\Payments\Helper\Order $orderHelper,
        \StripeIntegration\Payments\Helper\PaymentIntent $paymentIntentHelper
    )
    {
        $stripeObjectService = $stripeObjectServicePool->getStripeObjectService('events');
        $this->setData($stripeObjectService);

        $this->paymentElementFactory = $paymentElementFactory;
        $this->paymentIntentModel = $paymentIntentModel;
        $this->config = $config;
        $this->webhooksHelper = $webhooksHelper;
        $this->subscriptionsHelper = $subscriptionsHelper;
        $this->helper = $helper;
        $this->setupIntentCollection = $setupIntentCollection;
        $this->quoteHelper = $quoteHelper;
        $this->orderHelper = $orderHelper;
        $this->customerModel = $this->helper->getCustomerModel();
        $this->paymentIntentHelper = $paymentIntentHelper;
    }

    public function process($arrEvent, $object)
    {
        try
        {
            $order = $this->webhooksHelper->loadOrderFromEvent($arrEvent);
        }
        catch (MissingOrderException $e)
        {
            // We get here when the customer adds a new payment method from the customer account section.
            return;
        }

        $setupIntentModel = $this->setupIntentCollection->findBySetupIntentId($object['id']);
        if ($setupIntentModel->getIsDelayedSubscriptionSetup())
        {
            $setupIntentModel->hydrate();
            return $this->setupDelayedSubscription($setupIntentModel->getStripeObject(), $order);
        }

        if ($order->getPayment()->getAdditionalInformation("payment_action") == "order")
        {
            $setupIntent = $this->config->getStripeClient()->setupIntents->retrieve($object['id']);
            $this->paymentIntentModel->processSuccessfulOrder($order, $setupIntent);
            $this->helper->setProcessingState($order, __("The payment method has been authenticated and saved."));
            $this->orderHelper->saveOrder($order);
        }

        return $this->updateTrialSubscriptionMetadata($object['id'], $order, $object);
    }

    // When a trial subscription is purchased, there will be no charge.succeeded event, so we
    // perform the post processing on setup_intent.succeeded.
    protected function updateTrialSubscriptionMetadata($setupIntentId, $order, $object)
    {
        $paymentElement = $this->paymentElementFactory->create()->load($setupIntentId, 'setup_intent_id');
        if (!$paymentElement->getId())
            return null;

        if (!$paymentElement->getSubscriptionId())
            return null;

        $subscription = $this->config->getStripeClient()->subscriptions->retrieve($paymentElement->getSubscriptionId());

        $updateData = [];

        if (empty($subscription->metadata->{"Order #"}))
        {
            // With PaymentElement subscriptions, the subscription object is created before the order is placed,
            // and thus it does not have the order number at creation time.
            $updateData["metadata"] = ["Order #" => $order->getIncrementId()];
        }

        if (!empty($object['payment_method']))
        {
            $updateData['default_payment_method'] = $object['payment_method'];
        }

        if (!empty($updateData))
        {
            $subscription = $this->config->getStripeClient()->subscriptions->update($subscription->id, $updateData);
        }

        $this->webhooksHelper->processTrialingSubscriptionOrder($order, $subscription);

        return $subscription;
    }

    protected function setupDelayedSubscription(\Stripe\SetupIntent $setupIntent, $order)
    {
        $quote = $this->quoteHelper->loadQuoteById($order->getQuoteId());
        if (!$quote->getId())
        {
            throw new GenericException("Could not set up subscription for order becuase the order's quote could not be loaded.");
        }

        if (empty($setupIntent->payment_method))
        {
            throw new GenericException("Could not set up subscription for order because the payment method is missing.");
        }

        if (empty($setupIntent->customer))
        {
            throw new GenericException("Could not set up subscription for order because the customer is missing.");
        }

        $this->customerModel->fromStripeCustomerId($setupIntent->customer);
        $order->getPayment()->setAdditionalInformation("token", $setupIntent->payment_method);
        $params = $this->paymentIntentModel->getParamsFrom($order);
        /** @var \Stripe\Subscription $subscription */
        $subscription = $this->subscriptionsHelper->updateSubscriptionFromOrder($order, null, $params);
        if (!empty($subscription->id))
        {
            $msg = __("Successfully verified the payment method");
            $this->orderHelper->addOrderComment($msg, $order);
            $this->paymentIntentModel->processFutureSubscriptionOrder($order, $subscription->customer, $subscription->id);
            $this->orderHelper->saveOrder($order);

            // The payment confirmation must be last, because a charge.succeeded event will be triggered, and we want
            // to avoid race conditions with charge.succeeded.
            if ($subscription->status == "incomplete")
            {
                if (!empty($subscription->latest_invoice->payment_intent->id))
                {
                    // We get here when a subscription with a future start date is purchased together with a regular product
                    $invoice = $this->config->getStripeClient()->invoices->pay($subscription->latest_invoice->id, [
                        'expand' => ['payment_intent', 'subscription']
                    ]);

                    // Reload the subscription
                    $subscription = $invoice->subscription;
                }
                else
                {
                    $msg = __("The payment method has been verified but the subscription is incomplete. Please try again.");
                    $this->orderHelper->addOrderComment($msg, $order);
                    $this->orderHelper->saveOrder($order);
                    return $subscription;
                }
            }
            else if ($subscription->latest_invoice->status == "open")
            {
                // We get here when a subscription has a configured start date of which the first payment is on the order date
                $invoice = $this->config->getStripeClient()->invoices->pay($subscription->latest_invoice->id, [
                    'expand' => ['payment_intent', 'subscription']
                ]);

                // Reload the subscription
                $subscription = $invoice->subscription;
            }

            if (!in_array($subscription->status, ["active", "trialing"]))
            {
                $msg = __("The payment method has been verified but the subscription is not active. Please try again.");
                $this->orderHelper->addOrderComment($msg, $order);
                $this->orderHelper->saveOrder($order);
                return $subscription;
            }
        }

        return $subscription;
    }
}