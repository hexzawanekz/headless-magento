<?php

/*
 * Created by 2C2P
 * Date 19 June 2017
 * P2c2pRequest helper class is used to generate the current user request and send it to 2c2p payment getaway.
 */

namespace P2c2p\P2c2pPayment\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use P2c2p\P2c2pPayment\Helper\P2c2pHash;
use P2c2p\P2c2pPayment\Helper\P2c2pCurrencyCode;
use Magento\Store\Model\StoreManagerInterface;

class P2c2pRequest extends AbstractHelper{

	private $objConfigSettings;
	private $objP2c2pHashHelper;
	private $objStoreManagerInterface;
	private $objP2c2pCurrencyCodeHelper;

	function __construct(ScopeConfigInterface $configSettings, P2c2pHash $p2c2pHash, 
				StoreManagerInterface $storeManagerInterface, 
				P2c2pCurrencyCode $p2c2pCurrencyCode) {

		$this->objConfigSettings = $configSettings->getValue('payment/p2c2ppayment');
		$this->objP2c2pHashHelper = $p2c2pHash;
		$this->objStoreManagerInterface = $storeManagerInterface;
		$this->objP2c2pCurrencyCodeHelper = $p2c2pCurrencyCode;
	}

	//Declare the Form array to hold the 2c2p form request.
	private $arrayP2c2pFormFields = array(
		"version"     			=> "",
		"merchant_id" 			=> "", 
		"payment_description" 	=> "",
		"order_id" 				=> "", 
		"invoice_no" 			=> "",
		"currency" 				=> "",
		"amount" 				=> "", 
		"customer_email" 		=> "",
		"pay_category_id" 		=> "",
		"promotion" 			=> "",
		"user_defined_1" 		=> "",
		"user_defined_2" 		=> "",
		"user_defined_3" 		=> "",
		"user_defined_4" 		=> "",
		"user_defined_5" 		=> "",
		"result_url_1" 			=> "",
		"result_url_2" 			=> "",
		"payment_option" 		=> "",
		"enable_store_card" 	=> "",
		"stored_card_unique_id" => "",
		"request_3ds"   		=> "",
		"payment_expiry" 		=> "",
		"default_lang" 			=> "",
		"statement_descriptor" 	=> "",
		"hash_value" 			=> "" 
		);

	//This function is used to genereate the request for make payment to payment getaway.
	public function p2c2p_construct_request($parameter,$isLoggedIn) {

		if($isLoggedIn) {

			//Check stored card is enble by Merchant or not.
			if ($this->objConfigSettings['storedCard']) {
				$enable_store_card = "Y";
				$this->arrayP2c2pFormFields["enable_store_card"] = $enable_store_card;

				if(!empty($parameter['stored_card_unique_id'])) {
					$this->arrayP2c2pFormFields["stored_card_unique_id"] = $parameter['stored_card_unique_id'];
				}
			}
		}

		$this->generateP2c2pCommonFormFields($parameter);
		$this->setPaymentExpiryTime($parameter);

		$hash_value = $this->objP2c2pHashHelper->createRequestHashValue($this->arrayP2c2pFormFields,$this->objConfigSettings['secretKey']);
		$this->arrayP2c2pFormFields['hash_value']  = $hash_value;

		$strHtml = '<form name="p2c2pform" action="'. $this->getPaymentGetwayRedirectUrl() .'" method="post"/>';

		foreach ($this->arrayP2c2pFormFields as $key => $value) {
			if (!empty($value)) {
				$strHtml .= '<input type="hidden" name="' . htmlentities($key) . '" value="' . htmlentities($value) . '">';
			}
		}

		$strHtml .= '<input type="hidden" name="request_3ds" value="">';
		$strHtml .= '</form>';
		$strHtml .= '<script type="text/javascript">';
		$strHtml .= 'document.p2c2pform.submit()';
		$strHtml .= '</script>';			
		return $strHtml;
	}

	//This function is used calculate the amount by selected currency code by merchant in merchant store. 
	public function getP2c2pAmountByCurrencyCode($amount) {

		$exponent = 0;
		$isFounded  = false;
		$currency_type = $this->getMerchantSelectedCurrencyCode();

		foreach ($this->objP2c2pCurrencyCodeHelper->getP2c2pSupportedCurrenyCode() as $key => $value) {			
			if ($value['Num'] === $currency_type) {
				$exponent = $value['Exponent'];
				$isFounded = true;
				break;
			}
		}

		if ($isFounded) {
			if ($exponent == 0 || empty($exponent)) {
				$amount = (int) $amount;
			} else {
				$pg_2c2p_exponent = $this->objP2c2pCurrencyCodeHelper->getP2c2pSupportedCurrencyExponents();
				$multi_value      = $pg_2c2p_exponent[$exponent];
				$amount           = ($amount * $multi_value);
			}
		}

		return str_pad($amount, 12, '0', STR_PAD_LEFT);
	}

	//Creating basic form field request this's required by 2C2P Payment getaway.
	private function generateP2c2pCommonFormFields($parameter) {
		
		$merchant_id      	 = $this->objConfigSettings['merchantId'];		
		$currency       	 = $this->getMerchantSelectedCurrencyCode();
		$selected_lang       = $this->objConfigSettings['toc2p_lang'];;

		$default_lang  = !empty($selected_lang) ? $selected_lang : 'en';

		$this->arrayP2c2pFormFields["version"] 				= "7.0";
		$this->arrayP2c2pFormFields["merchant_id"] 			= $merchant_id;
		$this->arrayP2c2pFormFields["payment_description"]  = $parameter['payment_description'];
		$this->arrayP2c2pFormFields["order_id"] 			= $parameter['order_id'];
		$this->arrayP2c2pFormFields["invoice_no"] 			= $parameter['invoice_no'];
		$this->arrayP2c2pFormFields["currency"] 			= $currency;
		$this->arrayP2c2pFormFields["amount"] 				= $this->getP2c2pAmountByCurrencyCode($parameter['amount']);
		$this->arrayP2c2pFormFields["customer_email"] 		= $parameter['customer_email'];
		$this->arrayP2c2pFormFields["pay_category_id"] 		= "";
		$this->arrayP2c2pFormFields["promotion"] 			= "";
		$this->arrayP2c2pFormFields["user_defined_1"] 		= "";
		$this->arrayP2c2pFormFields["user_defined_2"] 		= "";
		$this->arrayP2c2pFormFields["user_defined_3"] 		= "";
		$this->arrayP2c2pFormFields["user_defined_4"] 		= "";
		$this->arrayP2c2pFormFields["user_defined_5"] 		= "";
		$this->arrayP2c2pFormFields["request_3ds"]    		= "";
        $this->arrayP2c2pFormFields["result_url_1"]   		= $this->getMerchantReturnUrl();
        $this->arrayP2c2pFormFields["result_url_2"]   		= $this->getMerchantReturnUrl();
        $this->arrayP2c2pFormFields["payment_option"]   	= "A"; // Pass by default Payment option as A
        $this->arrayP2c2pFormFields["default_lang"]   		= $default_lang; // Set selected language.
    }

    /*Get the selected currency code and converted this's selected currency to number instead of 3 character like 'SGD'. Because 2C2P is accept currency code in Digit only. */
    function getMerchantSelectedCurrencyCode() {
    	
    	$currency_code = $this->objStoreManagerInterface->getStore()->getCurrentCurrency()->getCode();

    	foreach ($this->objP2c2pCurrencyCodeHelper->getP2c2pSupportedCurrenyCode() as $key => $value) {
    		if($key === $currency_code){
    			return  $value['Num'];
    		}
    	}

    	return "";
    }

    //Set the 123 payment type expiry date of the currenct time zone.
    function setPaymentExpiryTime($paymentBody) {

    	$payment_expiry = $this->objConfigSettings['paymentExpiry'];

    	$date           = date("Y-m-d H:i:s");
    	$strTimezone    = date_default_timezone_get();
    	$date           = new \DateTime($date, new \DateTimeZone($strTimezone));
    	$date->modify("+" . $payment_expiry . "hours");
    	$payment_expiry = $date->format("Y-m-d H:i:s");

    	$this->arrayP2c2pFormFields["payment_expiry"] = $payment_expiry;
    }

    //Get Payment Getway redirect url to redirect Test URL or Live URL to 2c2p PG. It is depending upon the Merchant selected settings in configurations.
    function getPaymentGetwayRedirectUrl() {

    	if ($this->objConfigSettings['mode']) {
    		return 'https://demo2.2c2p.com/2C2PFrontEnd/RedirectV3/payment';
    	} else {  		
    		return 'https://t.2c2p.com/RedirectV3/payment';
    	}
    }

    //Get the merchant website return URL.
    function getMerchantReturnUrl() {

    	$baseUrl = $this->objStoreManagerInterface->getStore()->getBaseUrl();
    	return  $baseUrl.'p2c2p/payment/response';
    }
}