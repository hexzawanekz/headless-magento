<?php

namespace StripeIntegration\Payments\Model\Adminhtml\Source\Express\ApplePay;

class ButtonTheme
{
    public function toOptionArray()
    {
        return [
            [
                'value' => 'black',
                'label' => __('Black')
            ],
            [
                'value' => 'white',
                'label' => __('White')
            ],
            [
                'value' => 'white-outline',
                'label' => __('White with outline')
            ]
        ];
    }
}
