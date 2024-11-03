<?php

namespace StripeIntegration\Payments\Model\Stripe\Event;

use StripeIntegration\Payments\Model\Stripe\StripeObjectTrait;

class PaymentIntentCanceled
{
    use StripeObjectTrait;

    private $webhooksHelper;
    private $helper;

    public function __construct(
        \StripeIntegration\Payments\Model\Stripe\Service\StripeObjectServicePool $stripeObjectServicePool,
        \StripeIntegration\Payments\Helper\Webhooks $webhooksHelper,
        \StripeIntegration\Payments\Helper\Generic $helper
    )
    {
        $stripeObjectService = $stripeObjectServicePool->getStripeObjectService('events');
        $this->setData($stripeObjectService);

        $this->webhooksHelper = $webhooksHelper;
        $this->helper = $helper;
    }

    public function process($arrEvent, $object)
    {
        if ($object["status"] != "canceled")
            return;

        $orders = $this->webhooksHelper->loadOrderFromEvent($arrEvent, true);

        foreach ($orders as $order)
        {
            if ($order->getState() != "pending_payment")
            {
                $msg = __("A payment that was associated with this order (%1) has been canceled.", $object["id"]);
                $this->webhooksHelper->addOrderComment($order, $msg);
                continue;
            }

            if ($object["cancellation_reason"] == "abandoned")
            {
                $msg = __("Customer abandoned the cart. The payment session has expired.");
                $this->webhooksHelper->addOrderComment($order, $msg);
                $this->helper->cancelOrCloseOrder($order);
            }
        }
    }
}