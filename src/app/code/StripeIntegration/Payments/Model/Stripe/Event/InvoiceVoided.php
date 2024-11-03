<?php

namespace StripeIntegration\Payments\Model\Stripe\Event;

use StripeIntegration\Payments\Model\Stripe\StripeObjectTrait;

class InvoiceVoided
{
    use StripeObjectTrait;

    private $webhooksHelper;
    private $helper;
    private $orderHelper;

    public function __construct(
        \StripeIntegration\Payments\Model\Stripe\Service\StripeObjectServicePool $stripeObjectServicePool,
        \StripeIntegration\Payments\Helper\Webhooks $webhooksHelper,
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Helper\Order $orderHelper
    )
    {
        $stripeObjectService = $stripeObjectServicePool->getStripeObjectService('events');
        $this->setData($stripeObjectService);

        $this->webhooksHelper = $webhooksHelper;
        $this->helper = $helper;
        $this->orderHelper = $orderHelper;
    }

    public function process($arrEvent, $object)
    {
        $order = $this->webhooksHelper->loadOrderFromEvent($arrEvent);

        switch ($order->getPayment()->getMethod())
        {
            case "stripe_payments_invoice":
                $this->webhooksHelper->refundOfflineOrCancel($order);
                $comment = __("The invoice was voided from the Stripe Dashboard.");
                $order->addStatusToHistory(false, $comment, $isCustomerNotified = false);
                $this->orderHelper->saveOrder($order);
                break;
        }
    }
}