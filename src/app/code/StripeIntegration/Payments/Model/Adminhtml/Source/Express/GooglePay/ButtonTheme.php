<?php

namespace StripeIntegration\Payments\Model\Adminhtml\Source\Express\GooglePay;

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
            ]
        ];
    }
}
