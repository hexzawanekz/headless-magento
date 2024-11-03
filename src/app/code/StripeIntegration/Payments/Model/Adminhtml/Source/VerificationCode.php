<?php

namespace StripeIntegration\Payments\Model\Adminhtml\Source;

use Magento\Framework\Data\OptionSourceInterface;

class VerificationCode implements OptionSourceInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'new_cards', 'label' => __('Collect only for new cards')],
            ['value' => 'new_saved_cards', 'label' => __('Collect for new and saved cards')]
        ];
    }
}
