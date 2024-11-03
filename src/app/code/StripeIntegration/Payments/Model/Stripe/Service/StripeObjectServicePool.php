<?php

namespace StripeIntegration\Payments\Model\Stripe\Service;

use StripeIntegration\Payments\Model\Stripe\Service\StripeObjectServiceFactory;

class StripeObjectServicePool
{
    private $stripeObjectServiceFactory;

    public function __construct(
        StripeObjectServiceFactory $stripeObjectServiceFactory
    )
    {
        $this->stripeObjectServiceFactory = $stripeObjectServiceFactory;
    }

    public function getStripeObjectService($objectSpace)
    {
        return $this->stripeObjectServiceFactory
                ->create()
                ->setObjectSpace($objectSpace);
    }
}