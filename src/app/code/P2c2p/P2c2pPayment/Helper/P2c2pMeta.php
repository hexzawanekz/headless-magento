<?php

/*
 * Created by 2C2P
 * Date 19 June 2017
 * P2c2pMeta helper class is used to store data into table. i.e created by P2c2p plugin.
 */

namespace P2c2p\P2c2pPayment\Helper;

use P2c2p\P2c2pPayment\Model\TokenFactory;
use P2c2p\P2c2pPayment\Model\MetaFactory;

class P2c2pMeta
{	
	protected $objTokenFactory, $objMetaFactory;

	public function __construct(TokenFactory $objTokenFactory, MetaFactory $objMetaFactory) {
		$this->objTokenFactory = $objTokenFactory;
		$this->objMetaFactory  = $objMetaFactory;
	}

	//Save the Payment getaway response into p2c2p_meta table using the current payment order_id.
	public function savePaymentGetawayResponse($request,$user_id) {
		
		$model = $this->objMetaFactory->create();	

		$model->setData('p2c2p_id' , array_key_exists('order_id',$request) ? $request['order_id'] : '' );
		$model->setData('order_id' , array_key_exists('order_id',$request) ? $request['order_id'] : '' );
		$model->setData('user_id' , $user_id);
		$model->setData('version' , array_key_exists('version',$request) ? $request['version'] : '' );
		$model->setData('request_timestamp' , array_key_exists('request_timestamp',$request) ? $request['request_timestamp'] : '' );
		$model->setData('merchant_id' , array_key_exists('merchant_id',$request) ? $request['merchant_id'] : '' );
		$model->setData('invoice_no' , array_key_exists('invoice_no',$request) ? $request['invoice_no'] : '' );
		$model->setData('currency' , array_key_exists('currency',$request) ? $request['currency'] : '' );
		$model->setData('amount' , array_key_exists('amount',$request) ? $request['amount'] : '' );
		$model->setData('transaction_ref' , array_key_exists('transaction_ref',$request) ? $request['transaction_ref'] : '' );
		$model->setData('approval_code' , array_key_exists('approval_code',$request) ? $request['approval_code'] : '' );
		$model->setData('eci' , array_key_exists('eci',$request) ? $request['eci'] : '' );
		$model->setData('transaction_datetime' , array_key_exists('transaction_datetime',$request) ? $request['transaction_datetime'] : '' );
		$model->setData('payment_channel' , array_key_exists('payment_channel',$request) ? $request['payment_channel'] : '' );
		$model->setData('payment_status' , array_key_exists('payment_status',$request) ? $request['payment_status'] : '' );
		$model->setData('channel_response_code' , array_key_exists('channel_response_code',$request) ? $request['channel_response_code'] : '' );
		$model->setData('channel_response_desc' , array_key_exists('channel_response_desc',$request) ? $request['channel_response_desc'] : '' );
		$model->setData('masked_pan' , array_key_exists('masked_pan',$request) ? $request['masked_pan'] : '' );
		$model->setData('stored_card_unique_id' , array_key_exists('stored_card_unique_id',$request) ? $request['stored_card_unique_id'] : '' );
		$model->setData('backend_invoice' , array_key_exists('backend_invoice',$request) ? $request['backend_invoice'] : '' );
		$model->setData('paid_channel' ,  array_key_exists('paid_channel',$request) ? $request['paid_channel'] : '' );
		$model->setData('paid_agent' , array_key_exists('paid_agent',$request) ? $request['paid_agent'] : '' );
		$model->setData('recurring_unique_id' , array_key_exists('recurring_unique_id',$request) ? $request['recurring_unique_id'] : '' );
		$model->setData('user_defined_1' , array_key_exists('user_defined_1',$request) ? $request['user_defined_1'] : '' );
		$model->setData('user_defined_2' , array_key_exists('user_defined_2',$request) ? $request['user_defined_2'] : '' );
		$model->setData('user_defined_3' , array_key_exists('user_defined_3',$request) ? $request['user_defined_3'] : '' );
		$model->setData('user_defined_4' , array_key_exists('user_defined_4',$request) ? $request['user_defined_4'] : '' );
		$model->setData('user_defined_5' , array_key_exists('user_defined_5',$request) ? $request['user_defined_5'] : '' );
		$model->setData('browser_info' , array_key_exists('browser_info',$request) ? $request['browser_info'] : '' );
		$model->setData('ippPeriod' , array_key_exists('ippPeriod',$request) ? $request['ippPeriod'] : '' );
		$model->setData('ippInterestType' , array_key_exists('ippInterestType',$request) ? $request['ippInterestType'] : '' );
		$model->setData('ippInterestRate' , array_key_exists('ippInterestRate',$request) ? $request['ippInterestRate'] : '' );
		$model->setData('ippMerchantAbsorbRate' , array_key_exists('ippMerchantAbsorbRate',$request) ? $request['ippMerchantAbsorbRate'] : '' );

		$model->save();

	}

	// save the logged in user token into p2c2p_token table for stored card payment getaway.
	public function saveUserToken($arrayTokenData) {
		$model = $this->objTokenFactory->create();

		$model->setData($arrayTokenData);
		$model->save();
	}

	// get the logged in customer token data from p2c2p_token table.
	public function getUserToken($intUserId) {
		if(empty($intUserId)) 
			return;

		$objTokenFactoryModel = $this->objTokenFactory->create()->getCollection()->addFieldToFilter('user_id',$intUserId);

		return $objTokenFactoryModel;
	}

	// get the token detail passing the token key
	public function getTokenByTokenId($intTokenId) {
		if(!isset($intTokenId))
			return;

		return $this->objTokenFactory->create()->load($intTokenId);	
	}
}