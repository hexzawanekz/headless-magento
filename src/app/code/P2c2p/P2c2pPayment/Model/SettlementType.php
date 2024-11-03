<?php

/*
 * Created by 2C2P
 * Date 30 June 2019
 * SettlementType is give the model for dropdown page in admin configuration setting page.
 */

namespace P2c2p\P2c2pPayment\Model;

class SettlementType extends \Magento\Payment\Model\Method\AbstractMethod
{
	public function toOptionArray()
	{
		return [ ['value' => 'authorize', 'label' => __('Manual')], ['value' => 'authorize_capture', 'label' => __('Auto (Default)')], ];
	}
}