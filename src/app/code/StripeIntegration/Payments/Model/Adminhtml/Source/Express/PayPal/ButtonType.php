<?php

namespace StripeIntegration\Payments\Model\Adminhtml\Source\Express\PayPal;

class ButtonType
{
    public function toOptionArray()
    {
        return [
            [
                'value' => 'paypal',
                'label' => __('PayPal')
            ],
            [
                'value' => 'checkout',
                'label' => __('Checkout')
            ],
            [
                'value' => 'buynow',
                'label' => __('Buy now')
            ],
            [
                'value' => 'pay',
                'label' => __('Pay')
            ]
        ];
    }
}
