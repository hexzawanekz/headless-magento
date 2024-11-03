<?php

namespace StripeIntegration\Payments\Model\Adminhtml\Source;

class StripeRadar
{
    public function toOptionArray()
    {
        return [
            [
                'value' => 0,
                'label' => __('Disabled')
            ],
            [
                'value' => 10,
                'label' => __('Enabled')
            ]
        ];
    }
}
