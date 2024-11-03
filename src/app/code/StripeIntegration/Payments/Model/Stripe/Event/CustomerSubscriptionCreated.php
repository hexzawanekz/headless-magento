<?php

namespace StripeIntegration\Payments\Model\Stripe\Event;

use StripeIntegration\Payments\Model\Stripe\StripeObjectTrait;

class CustomerSubscriptionCreated
{
    use StripeObjectTrait;

    private $webhooksHelper;
    private $helper;
    private $subscriptionsHelper;
    private $orderHelper;

    public function __construct(
        \StripeIntegration\Payments\Model\Stripe\Service\StripeObjectServicePool $stripeObjectServicePool,
        \StripeIntegration\Payments\Helper\Webhooks $webhooksHelper,
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Helper\Order $orderHelper,
        \StripeIntegration\Payments\Helper\Subscriptions $subscriptionsHelper
    )
    {
        $stripeObjectService = $stripeObjectServicePool->getStripeObjectService('events');
        $this->setData($stripeObjectService);

        $this->webhooksHelper = $webhooksHelper;
        $this->helper = $helper;
        $this->orderHelper = $orderHelper;
        $this->subscriptionsHelper = $subscriptionsHelper;
    }

    public function process($arrEvent, $object, $stdEvent)
    {
        $subscription = $stdEvent->data->object;

        try
        {
            $order = $this->webhooksHelper->loadOrderFromEvent($arrEvent);
            $this->subscriptionsHelper->updateSubscriptionEntry($subscription, $order);

            if (empty($subscription->latest_invoice) && $order->getPayment()->getAdditionalInformation("is_future_subscription_setup"))
            {
                $this->helper->cancelOrCloseOrder($order, true, true);
                $comment = __("No payment has been collected. A separate order will be created with the first payment.");
                $order->addStatusToHistory(false, $comment, $isCustomerNotified = false);
                $this->orderHelper->saveOrder($order);
            }
        }
        catch (\Exception $e)
        {
            if ($object['status'] == "incomplete" || $object['status'] == "trialing")
            {
                // A PaymentElement has created an incomplete subscription which has no order yet
                $this->subscriptionsHelper->updateSubscriptionEntry($subscription, null);
            }
            else
            {
                throw $e;
            }
        }
    }
}