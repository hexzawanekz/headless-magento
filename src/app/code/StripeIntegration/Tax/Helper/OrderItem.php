<?php

namespace StripeIntegration\Tax\Helper;

class OrderItem
{
    public function hasCustomizableOptions($orderItem)
    {
        $productOptions = $orderItem->getProductOptions();

        return array_key_exists('options', $productOptions);
    }

    public function getCustomizableOptionsSuffix($orderItem)
    {
        $productOptions = $orderItem->getProductOptions();
        $suffix = '';
        foreach ($productOptions['options'] as $productOption) {
            $suffix .= '_' . $productOption['option_value'];
        }

        return $suffix;
    }
}