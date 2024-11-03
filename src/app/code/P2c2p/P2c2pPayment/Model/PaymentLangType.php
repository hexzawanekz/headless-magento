<?php

/*
 * Created by 2C2P
 * Date 19 June 2017
 * PaymentModeType is give the model for dropdown page in admin configuration setting page.
 */

namespace P2c2p\P2c2pPayment\Model;

class PaymentLangType extends \Magento\Payment\Model\Method\AbstractMethod
{
	public function toOptionArray()
	{
		return array(
			array('value' => 'en', 'label' => 'English'),
			array('value' => 'ja', 'label' => 'Japanese'),
			array('value' => 'th', 'label' => 'Thailand'),
			array('value' => 'id', 'label' => 'Bahasa Indonesia'),
			array('value' => 'my', 'label' => 'Burmese'),
			array('value' => 'zh', 'label' => 'Simplified Chinese'),
		);
	}
}