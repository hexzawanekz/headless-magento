<?php

namespace StripeIntegration\Payments\Model\Stripe\Event;

use StripeIntegration\Payments\Model\Stripe\StripeObjectTrait;

class SourceChargeable
{
    use StripeObjectTrait;

    private $webhooksHelper;
    private $helper;

    public function __construct(
        \StripeIntegration\Payments\Model\Stripe\Service\StripeObjectServicePool $stripeObjectServicePool,
        \StripeIntegration\Payments\Helper\Webhooks $webhooksHelper
    )
    {
        $stripeObjectService = $stripeObjectServicePool->getStripeObjectService('events');
        $this->setData($stripeObjectService);

        $this->webhooksHelper = $webhooksHelper;
    }

    public function process($arrEvent, $object)
    {
        $order = $this->webhooksHelper->loadOrderFromEvent($arrEvent);

        $this->webhooksHelper->charge($order, $object);
    }
}