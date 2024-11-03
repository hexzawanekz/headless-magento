<?php

namespace StripeIntegration\Payments\Model\Stripe\Event;

use StripeIntegration\Payments\Model\Stripe\StripeObjectTrait;

class CheckoutSessionCompleted
{
    use StripeObjectTrait;

    private $startDateFactory;
    private $subscriptionScheduleModelFactory;
    private $webhooksHelper;
    private $helper;
    private $config;
    private $subscriptionsHelper;
    private $quoteHelper;
    private $orderHelper;

    public function __construct(
        \StripeIntegration\Payments\Model\Stripe\Service\StripeObjectServicePool $stripeObjectServicePool,
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Helper\Webhooks $webhooksHelper,
        \StripeIntegration\Payments\Helper\Subscriptions $subscriptionsHelper,
        \StripeIntegration\Payments\Helper\Quote $quoteHelper,
        \StripeIntegration\Payments\Helper\Order $orderHelper,
        \StripeIntegration\Payments\Model\Subscription\StartDateFactory $startDateFactory,
        \StripeIntegration\Payments\Model\Subscription\ScheduleFactory $subscriptionScheduleModelFactory
    )
    {
        $stripeObjectService = $stripeObjectServicePool->getStripeObjectService('events');
        $this->setData($stripeObjectService);

        $this->startDateFactory = $startDateFactory;
        $this->subscriptionScheduleModelFactory = $subscriptionScheduleModelFactory;
        $this->webhooksHelper = $webhooksHelper;
        $this->helper = $helper;
        $this->config = $config;
        $this->subscriptionsHelper = $subscriptionsHelper;
        $this->quoteHelper = $quoteHelper;
        $this->orderHelper = $orderHelper;
    }

    public function process($arrEvent, array $object)
    {
        $order = $this->webhooksHelper->loadOrderFromEvent($arrEvent);

        $quote = $this->quoteHelper->loadQuoteById($order->getQuoteId());
        if ($quote && $quote->getIsActive())
        {
            $quote->setIsActive(false);
            $this->quoteHelper->saveQuote($quote);
        }

        // A subscription with a start date might have been purchased
        $this->processSubscriptionPhases($order, $object);

        if (!empty($object['customer']))
        {
            $order->getPayment()->setAdditionalInformation('customer_stripe_id', $object['customer']);
            $order->getPayment()->save();
        }

        if (!empty($object['setup_intent']))
        {
            // Hits when the payment action is order, and when a subscription with a start date is purchased
            $setupIntent = $this->config->getStripeClient()->setupIntents->retrieve($object['setup_intent']);
            if (!empty($setupIntent->payment_method))
            {
                $order->getPayment()->setAdditionalInformation('token', $setupIntent->payment_method);
                $this->helper->setProcessingState($order, __("A payment method has been saved against the order."));
                $this->orderHelper->saveOrder($order);
            }
        }
    }

    private function processSubscriptionPhases($order, array $object)
    {
        if (empty($object['subscription']))
        {
            return false;
        }

        $subscription = $this->subscriptionsHelper->getSubscriptionFromOrder($order);
        if (!$subscription)
        {
            return false;
        }

        $startDateModel = $this->startDateFactory->create()->fromProfile($subscription['profile']);

        if (!$startDateModel->hasPhases())
        {
            return false;
        }

        $subscriptionScheduleModel = $this->subscriptionScheduleModelFactory->create([
            'subscriptionCreateParams' => [],
            'startDate' => $startDateModel,
        ]);
        $subscriptionScheduleModel->createFromSubscription($object['subscription'], $startDateModel);

        $order->getPayment()->setAdditionalInformation('subscription_schedule_id', $subscriptionScheduleModel->getId());
        $order->getPayment()->save(); // Saving the payment instead of the order, because that could cause webhooks race conditions

        return true;
    }
}