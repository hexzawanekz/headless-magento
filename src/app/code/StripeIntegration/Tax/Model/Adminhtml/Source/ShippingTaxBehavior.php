<?php

namespace StripeIntegration\Tax\Model\Adminhtml\Source;

use StripeIntegration\Tax\Helper\Tax;

class ShippingTaxBehavior
{
    public const TAX_FREE_VALUE = 'free';

    public function toOptionArray()
    {
        return [
            [
                'value' => self::TAX_FREE_VALUE,
                'label' => __('Tax free')
            ],
            [
                'value' => Tax::TAX_BEHAVIOR_EXCLUSIVE,
                'label' => __('Tax exclusive')
            ],
            [
                'value' => Tax::TAX_BEHAVIOR_INCLUSIVE,
                'label' => __('Tax inclusive')
            ],
        ];
    }
}