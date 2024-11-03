<?php

namespace StripeIntegration\Tax\Helper;

class Session
{
    private $checkoutSession;

    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        $this->checkoutSession = $checkoutSession;
    }

    public function setCheckoutData($name, $value)
    {
        $this->checkoutSession->setData('stripe_tax_' . $name, $value);
    }

    public function getCheckoutData($name)
    {
        return $this->checkoutSession->getData('stripe_tax_' . $name);
    }
}