<?php

namespace StripeIntegration\Payments\Model\Method;

class Express extends \StripeIntegration\Payments\Model\PaymentMethod
{
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if (!$this->getConfig()->initStripe())
            return false;

        if (!empty($quote) && $quote->getPayment() && $this->isExpressCheckout())
            return true;

        return false;
    }
}
