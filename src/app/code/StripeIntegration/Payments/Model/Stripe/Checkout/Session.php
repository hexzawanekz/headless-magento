<?php

// Class representing a Stripe Checkout Session object

namespace StripeIntegration\Payments\Model\Stripe\Checkout;

use StripeIntegration\Payments\Model\Stripe\StripeObjectTrait;

class Session
{
    use StripeObjectTrait;

    public $expandParams = ['payment_intent'];
    private $objectSpace = 'checkout.sessions';

    public function __construct(
        \StripeIntegration\Payments\Model\Stripe\Service\StripeObjectServicePool $stripeObjectServicePool
    )
    {
        $stripeObjectService = $stripeObjectServicePool->getStripeObjectService($this->objectSpace);
        $stripeObjectService->setExpandParams($this->expandParams);
        $this->setData($stripeObjectService);
    }

    public function fromParams($params)
    {
        $this->createObject($params);
        return $this;
    }
}