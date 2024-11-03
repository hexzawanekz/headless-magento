<?php

namespace StripeIntegration\Payments\Model\Adminhtml\Source;

use Magento\Framework\Data\OptionSourceInterface;

class PaymentFlow implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            [
                'value' => 0,
                'label' => __('Embed payment form into the native flow.')
            ],
            [
                'value' => 1,
                'label' => __('Redirect customers to Stripe Checkout.')
            ],
        ];
    }
}
