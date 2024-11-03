<?php

namespace StripeIntegration\Payments\Test\Integration\Mock\StripeIntegration\Payments\Model;

class Config extends \StripeIntegration\Payments\Model\Config
{
    public $manualAuthenticationPaymentMethods;

    public function getWebhooksSigningSecrets()
    {
        return [];
    }

    public function getManualAuthenticationPaymentMethods(): array
    {
        if (isset($this->manualAuthenticationPaymentMethods))
            return $this->manualAuthenticationPaymentMethods;

        return parent::getManualAuthenticationPaymentMethods();
    }
}