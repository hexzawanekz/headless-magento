<?php

namespace StripeIntegration\Payments\Helper;

class BankTransfers
{
    private $quoteHelper;

    public function __construct(
        \StripeIntegration\Payments\Helper\Quote $quoteHelper
    ) {
        $this->quoteHelper = $quoteHelper;
    }

    public function getPaymentMethodOptions()
    {
        $quote = $this->quoteHelper->getQuote();
        $billingAddress = $quote->getBillingAddress();

        // Get the country code
        $countryCode = $billingAddress->getCountryId();
        if (empty($countryCode))
            return null;

        switch ($countryCode)
        {
            case "US":
                $bankTransfer = [
                    'type' => 'us_bank_transfer',
                ];
                break;

            case "GB":
                $bankTransfer = [
                    'type' => 'gb_bank_transfer',
                ];
                break;

            case "JP":
                $bankTransfer = [
                    'type' => 'jp_bank_transfer',
                ];
                break;

            case "MX":
                $bankTransfer = [
                    'type' => 'mx_bank_transfer',
                ];
                break;
            case "BE": // Belgium
            case "DE": // Germany
            case "ES": // Spain
            case "FR": // France
            case "IE": // Ireland, Republic of (EIRE)
            case "NL": // Netherlands
                $bankTransfer = [
                    'type' => 'eu_bank_transfer',
                    'eu_bank_transfer' => [
                        'country' => $countryCode
                    ]
                ];
                break;
            default:
                return null;
        }

        return [
            "customer_balance" => [
                'funding_type' => 'bank_transfer',
                'bank_transfer' => $bankTransfer,
            ]
        ];
    }
}