<?php

/*
 * Created by 2C2P
 * Date 19 June 2017
 * PaymentModeType is give the model for dropdown page in admin configuration setting page.
 */

namespace P2c2p\P2c2pPayment\Model\Config;

class Toc2pLang implements \Magento\Framework\Option\ArrayInterface
{
	public function toOptionArray()
	{		
	    return [
            ['value' => 'en', 'label' => __('English')],
            ['value' => 'ja', 'label' => __('Japanese')],
            ['value' => 'th', 'label' => __('Thailand')],
            ['value' => 'id', 'label' => __('Bahasa Indonesia')],
            ['value' => 'my', 'label' => __('Burmese')],
            ['value' => 'zh', 'label' => __('Simplified Chinese')]
        ];
	}
}