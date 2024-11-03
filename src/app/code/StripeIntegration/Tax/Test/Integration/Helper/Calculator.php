<?php

namespace StripeIntegration\Tax\Test\Integration\Helper;

class Calculator extends AbstractCalculator
{
    public function __construct($country)
    {
        parent::__construct($country);
    }

    public function calculateQuoteData($product, $qty, $shippingRate,  $taxBehaviour)
    {
        return $this->calculateData($product, $qty, $shippingRate,  $taxBehaviour);
    }

    public function calculateQuoteDataMultipleTaxes($product, $qty, $shippingRate,  $taxBehaviour)
    {
        return $this->calculateDataMultipleTaxes($product, $qty, $shippingRate,  $taxBehaviour);
    }

    public function calculateQuoteItemData($price, $shipping, $qty, $taxBehaviour)
    {
        return $this->calculateItemData($price, $shipping, $qty, $taxBehaviour);
    }
}