<?php

namespace StripeIntegration\Tax\Test\Integration\Helper;

class PaymentMethod
{

    public function getPaymentMethodImportData($identifier, $billingAddressIdentifier = null)
    {
        $data = null;

        switch ($identifier)
        {
            case 'checkmo':
                $data = [
                    'method' => 'checkmo'
                ];

            default:
                break;

        }

        return $data;
    }
}