<?php

namespace StripeIntegration\Payments\Helper;

class PaymentMethodTypes
{
    private $expressCheckoutConfig;

    public function __construct(
        \StripeIntegration\Payments\Model\ExpressCheckout\Config $expressCheckoutConfig
    )
    {
        $this->expressCheckoutConfig = $expressCheckoutConfig;
    }

    public function getPaymentMethodTypes($isExpressCheckout = false)
    {
        if ($isExpressCheckout)
        {
            return $this->expressCheckoutConfig->getPaymentMethodTypes();
        }

        return null;
    }
}