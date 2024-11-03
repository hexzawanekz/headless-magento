<?php

namespace StripeIntegration\Payments\Model\Adminhtml\Source;

class Mode
{
    public const TEST = 'test';
    public const LIVE = 'live';

    public function toOptionArray()
    {
        return [
            [
                'value' => Mode::TEST,
                'label' => __('Test')
            ],
            [
                'value' => Mode::LIVE,
                'label' => __('Live')
            ],
        ];
    }
}
