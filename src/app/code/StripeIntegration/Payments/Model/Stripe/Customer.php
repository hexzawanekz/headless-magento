<?php

namespace StripeIntegration\Payments\Model\Stripe;

class Customer
{
    use StripeObjectTrait;

    private $objectSpace = 'customers';

    public function __construct(
        \StripeIntegration\Payments\Model\Stripe\Service\StripeObjectServicePool $stripeObjectServicePool
    )
    {
        $stripeObjectService = $stripeObjectServicePool->getStripeObjectService($this->objectSpace);
        $this->setData($stripeObjectService);
    }
}
