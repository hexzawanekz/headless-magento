<?php

/*
 * Created by 2C2P
 * Date 19 June 2017
 * PaymentModeType is give the model for dropdown page in admin configuration setting page.
 */

namespace P2c2p\P2c2pPayment\Model\Config;

class Mode implements \Magento\Framework\Option\ArrayInterface
{
	public function toOptionArray()
	{		
	    return [
            ['value' => '1', 'label' => __('Test Mode')],
            ['value' => '0', 'label' => __('Live Mode')],            
        ];
	}
}