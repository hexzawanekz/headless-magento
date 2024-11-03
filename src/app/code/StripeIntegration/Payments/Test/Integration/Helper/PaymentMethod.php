<?php

namespace StripeIntegration\Payments\Test\Integration\Helper;

class PaymentMethod
{
    private $stripeConfig;
    private $address;

    public function __construct(
        \StripeIntegration\Payments\Model\Config $stripeConfig,
        \StripeIntegration\Payments\Test\Integration\Helper\Address $address
    ) {
        $this->stripeConfig = $stripeConfig;
        $this->address = $address;
    }

    public function getPaymentMethodImportData($identifier, $billingAddressIdentifier = null)
    {
        $data = null;

        switch ($identifier)
        {
            case 'SuccessCard':
                $data = [
                    'method' => 'stripe_payments',
                    'additional_data' => [
                        "payment_method" => $this->stripeConfig->getStripeClient()->paymentMethods->retrieve('pm_card_visa')->id
                    ]
                ];
                break;

            case 'DeclinedCard':
                $data = [
                    'method' => 'stripe_payments',
                    'additional_data' => [
                        "payment_method" => $this->stripeConfig->getStripeClient()->paymentMethods->retrieve('pm_card_visa_chargeDeclined')->id
                    ]
                ];
                break;

            case 'InsufficientFundsCard':
                $data = [
                    'method' => 'stripe_payments',
                    'additional_data' => [
                        "payment_method" => $this->stripeConfig->getStripeClient()->paymentMethods->retrieve('pm_card_visa_chargeDeclinedInsufficientFunds')->id
                    ]
                ];
                break;

            case 'AuthenticationRequiredCard':
                $data = [
                    'method' => 'stripe_payments',
                    'additional_data' => [
                        "payment_method" => $this->stripeConfig->getStripeClient()->paymentMethods->retrieve('pm_card_authenticationRequired')->id
                    ]
                ];
                break;

            case 'ElevatedRiskCard':
                $data = [
                    'method' => 'stripe_payments',
                    'additional_data' => [
                        "payment_method" => $this->stripeConfig->getStripeClient()->paymentMethods->retrieve('pm_card_riskLevelElevated')->id
                    ]
                ];
                break;

            case 'SOFORT':
                $data = [
                    'method' => 'stripe_payments',
                    'additional_data' => [
                        "payment_method" => $this->createPaymentMethod('sofort', $billingAddressIdentifier)->id
                    ]
                ];
                break;

            case 'StripeCheckout':
                $data = [
                    'method' => 'stripe_payments_checkout'
                ];
                break;

            case 'BankTransfer':
                $paymentMethod = $this->createPaymentMethod('customer_balance', $billingAddressIdentifier);
                $data = [
                    'method' => 'stripe_payments_bank_transfers',
                    'additional_data' => [
                        "payment_method" => $paymentMethod->id
                    ]
                ];
                break;

            case 'SubscriptionUpdate':
                $data = [
                    'method' => 'stripe_payments',
                    'additional_data' => [
                        "is_subscription_update" => true
                    ]
                ];
                break;

            case 'StripeInvoice':
                $data = [
                    'method' => 'stripe_payments_invoice',
                    'days_due' => 7
                ];
                break;

            default:
                break;

        }

        return $data;
    }

    public function createPaymentMethodFromCardNumber($cardNumber, $billingAddress = null)
    {
        $params = [
          'type' => 'card',
          'card' => [
            'number' => $cardNumber,
            'exp_month' => 8,
            'exp_year' => date("Y", time()) + 1,
            'cvc' => '314',
          ],
        ];

        if ($billingAddress)
            $params['billing_details'] = $this->address->getStripeFormat($billingAddress);

        return $this->stripeConfig->getStripeClient()->paymentMethods->create($params);
    }

    public function createPaymentMethod($type, $billingAddress)
    {
        $stripe = $this->stripeConfig->getStripeClient();
        $params = [
            "billing_details" => $this->address->getStripeFormat($billingAddress),
            "type" => strtolower($type)
        ];

        switch ($type)
        {
            case "SuccessCard":
            case "card":
                $pm = $this->stripeConfig->getStripeClient()->paymentMethods->retrieve('pm_card_visa');
                return $pm;
            case "sofort":
                $params["sofort"] = [
                    'country' => $params["billing_details"]["address"]["country"]
                ];
                break;
            case "sepa_debit":
                $params["sepa_debit"] = [
                    'iban' => "DE89370400440532013000"
                ];
                break;
            case "us_bank_account":
                $params["us_bank_account"] = [
                    'account_holder_type' => "individual",
                    'account_number' => "0123456789",
                    'account_type' => "savings",
                    'routing_number' => "021000021"
                ];
                break;
            case "bacs_debit":
                $params["bacs_debit"] = [
                    'account_number' => "00012345",
                    'sort_code' => '108800'
                ];
                break;
            case "au_becs_debit":
                $params["au_becs_debit"] = [
                    'account_number' => "000123456",
                    'bsb_number' => '000000'
                ];
                break;
            case "klarna":
                $params["klarna"] = [];
                break;
            default:
                break;
        }

        return $stripe->paymentMethods->create($params);
    }
}