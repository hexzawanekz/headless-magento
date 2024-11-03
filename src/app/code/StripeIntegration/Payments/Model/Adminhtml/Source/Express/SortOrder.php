<?php

namespace StripeIntegration\Payments\Model\Adminhtml\Source\Express;

class SortOrder
{
    public function toOptionArray()
    {
        return [
            [
                'value' => 'optimized',
                'label' => __('Optimized for conversion')
            ],
            [
                'value' => 'custom',
                'label' => __('Use sort order field')
            ]
        ];
    }
}
