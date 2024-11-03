<?php

namespace StripeIntegration\Payments\Model\Adminhtml\Source;

use Magento\Framework\Data\OptionSourceInterface;

class PaymentElementLayout implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            [
                'value' => 0,
                'label' => __('Horizontal - Tabs')
            ],
            [
                'value' => 1,
                'label' => __('Vertical - Accordion')
            ],
        ];
    }
}
