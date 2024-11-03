<?php

/*
 * Created by 2C2P
 * Date 19 June 2017
 * PaymentModeType is give the model for dropdown page in admin configuration setting page.
 */

namespace P2c2p\P2c2pPayment\Model;

class PaymentModeType extends \Magento\Payment\Model\Method\AbstractMethod
{
	public function toOptionArray()
	{
		return array(
			array('value' => 1, 'label' => 'Test Mode'),
			array('value' => 0, 'label' => 'Live Mode'),
			);
	}
}