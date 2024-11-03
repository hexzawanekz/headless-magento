<?php

namespace StripeIntegration\Tax\Test\Integration\Helper;

class Rate
{
    public const TAX_RATE_SUFFIX = '_TaxRate';
    private $objectManager;
    private $address;
    private $config;

    public function __construct()
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->address = $this->objectManager->get(\StripeIntegration\Tax\Test\Integration\Helper\Address::class);
        $this->config = $this->objectManager->get(\StripeIntegration\Tax\Model\Config::class);
    }
    public function getTaxRate($country)
    {
        $requestData = $this->formData($country);

        if (defined($country . self::TAX_RATE_SUFFIX)) {
            $rate = constant($country . self::TAX_RATE_SUFFIX);

            // In case there were tests that set the rate to 0 we only return values greater than 0 here
            // and let the API call run if the constant is set to 0
            if ($rate > 0) {
                return $rate;
            }
        }

        if ($this->config->isEnabled()) {
            $calculation = $this->config->getStripeClient()->tax->calculations->create($requestData);
            $rate = $this->getRateFromResponse($calculation);
            define($country . self::TAX_RATE_SUFFIX, $rate);

            return $rate;
        }

        return 0;
    }

    private function formData($country)
    {
        $customerDetails = $this->getCustomerDetails($this->address->getMagentoFormat($country));
        $currency = 'usd';
        $lineItems = $this->getLineItems();

        return [
            'currency' => $currency,
            'customer_details' => $customerDetails,
            'line_items' => $lineItems
        ];
    }

    private function getCustomerDetails($address)
    {
        return [
            'address' => [
                'line1' => $address['street'][0],
                'city' => $address['city'],
                'state' => $address['region_id'],
                'country' => $address['country_id'],
                'postal_code' => $address['postcode']
            ],
            'address_source' => 'shipping',
        ];
    }

    private function getLineItems()
    {
        return [
            [
                'amount' => 100,
                'tax_code' => 'txcd_99999999',
                'reference' => 'TaxRateTest'
            ]
        ];
    }

    private function getRateFromResponse($calculation)
    {
        if (sizeof($calculation->tax_breakdown) == 1) {
            if ($calculation->tax_breakdown[0]->amount != 0) {
                return (float)$calculation->tax_breakdown[0]->tax_rate_details->percentage_decimal;
            }
        } elseif (sizeof($calculation->tax_breakdown) > 1) {
            // Placeholder for when we will have multiple taxes in tests
            return 10;
        }

        return 0;
    }

}