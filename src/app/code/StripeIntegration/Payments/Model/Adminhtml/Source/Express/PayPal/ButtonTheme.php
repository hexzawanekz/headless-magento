<?php

namespace StripeIntegration\Payments\Model\Adminhtml\Source\Express\PayPal;

class ButtonTheme
{
    public function toOptionArray()
    {
        return [
            [
                'value' => 'gold',
                'label' => __('Gold')
            ],
            [
                'value' => 'blue',
                'label' => __('Blue')
            ],
            [
                'value' => 'silver',
                'label' => __('Silver')
            ],
            [
                'value' => 'white',
                'label' => __('White')
            ],
            [
                'value' => 'black',
                'label' => __('Black')
            ]
        ];
    }
}
