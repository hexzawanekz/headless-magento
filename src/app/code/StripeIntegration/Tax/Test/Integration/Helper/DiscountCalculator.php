<?php

namespace StripeIntegration\Tax\Test\Integration\Helper;

class DiscountCalculator extends AbstractDiscountCalculator
{

    public function __construct($country)
    {
        parent::__construct($country);
    }

    public function calculateQuoteData($product, $qty, $shippingRate,  $taxBehaviour)
    {
        return $this->calculateData($product, $qty, $shippingRate,  $taxBehaviour);
    }

    public function calculateQuoteItemData($price, $discountedPrice, $shipping, $qty, $taxBehaviour, $otherPrices = null, $isLargestPrice = true)
    {
        return $this->calculateItemData($price, $discountedPrice, $shipping, $qty, $taxBehaviour, $otherPrices, $isLargestPrice);
    }

    public function calculateQuoteItemDataBuyXGetYFree($price, $discountedPrice, $shipping, $fullQty, $discountQty, $taxBehaviour)
    {
        return $this->calculateItemDataBuyXGetYFree($price, $discountedPrice, $shipping, $fullQty, $discountQty, $taxBehaviour);
    }
}