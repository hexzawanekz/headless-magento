<?php

namespace StripeIntegration\Payments\Model\Adminhtml\Source;

class Enabled extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{
    public function toOptionArray()
    {
        return [
            [
                'value' => 0,
                'label' => __('Disabled')
            ],
            [
                'value' => 1,
                'label' => __('Enabled')
            ]
        ];
    }

    public function getAllOptions()
    {
        return $this->toOptionArray();
    }
}
