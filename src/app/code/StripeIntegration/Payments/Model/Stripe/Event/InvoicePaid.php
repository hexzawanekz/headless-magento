<?php

namespace StripeIntegration\Payments\Model\Stripe\Event;

use StripeIntegration\Payments\Model\Stripe\StripeObjectTrait;

class InvoicePaid
{
    use StripeObjectTrait;

    private $webhooksHelper;
    private $helper;
    private $config;
    private $orderHelper;

    public function __construct(
        \StripeIntegration\Payments\Model\Stripe\Service\StripeObjectServicePool $stripeObjectServicePool,
        \StripeIntegration\Payments\Helper\Webhooks $webhooksHelper,
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Helper\Order $orderHelper
    )
    {
        $stripeObjectService = $stripeObjectServicePool->getStripeObjectService('events');
        $this->setData($stripeObjectService);

        $this->webhooksHelper = $webhooksHelper;
        $this->helper = $helper;
        $this->config = $config;
        $this->orderHelper = $orderHelper;
    }
    public function process($arrEvent, $object)
    {
        // Avoid mutating orders in multiple events, it can lead to race conditions and data corruption
    }
}