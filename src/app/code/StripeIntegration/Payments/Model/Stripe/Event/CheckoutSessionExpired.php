<?php

namespace StripeIntegration\Payments\Model\Stripe\Event;

use StripeIntegration\Payments\Model\Stripe\StripeObjectTrait;

class CheckoutSessionExpired
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
        $order = $this->webhooksHelper->loadOrderFromEvent($arrEvent);

        $this->webhooksHelper->addOrderComment($order, __("Stripe Checkout session has expired without a payment."));

        if ($this->isPendingCheckoutOrder($order))
            $this->helper->cancelOrCloseOrder($order);
    }

    private function isPendingCheckoutOrder($order)
    {
        $method = $order->getPayment()->getMethod();
        if (!$this->helper->isStripeCheckoutMethod($method))
            return false;

        if ($order->getState() != "pending_payment")
            return false;

        if ($order->getPayment()->getLastTransId())
            return false;

        return true;
    }
}