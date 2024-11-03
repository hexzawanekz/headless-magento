<?php

namespace StripeIntegration\Tax\Model\Adminhtml\Source;

use StripeIntegration\Tax\Helper\Tax;

class PricesAndPromotionsTaxBehavior
{
    public function toOptionArray()
    {
        return [
            [
                'value' => Tax::TAX_BEHAVIOR_INCLUSIVE,
                'label' => __('Tax inclusive')
            ],
            [
                'value' => Tax::TAX_BEHAVIOR_EXCLUSIVE,
                'label' => __('Tax exclusive')
            ],
        ];
    }
}