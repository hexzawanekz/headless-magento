<?php

/*
 * Created by 2C2P
 * Date 19 June 2017
 * PaymentMethod is base class / entry point for 2c2p plugin.
 */


namespace P2c2p\P2c2pPayment\Model;


class PaymentMethod extends \Magento\Payment\Model\Method\AbstractMethod
{	
	protected $_code = 'p2c2ppayment';
	protected $_isInitializeNeeded = true;
    protected $_canCapture = true;
    protected $_canAuthorize = true;
    protected $_canRefund  = true;
    protected $_canVoid = true;
    protected $_isGateway = true;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;


    private $objConfigSettings;
    private $merchant_id;
    private $secretKey;
    private $processType;
    private $invoiceNo;
    private $version;
    private $hash;


    public function log($data){
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/p2c2p.log');
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            $logger->info($data);
    }


    

    //protected $_formBlockType = 'P2c2p\P2c2pPayment\Block\Form';

    //Set additional data and session object and use it further process.
	public function assignData(\Magento\Framework\DataObject $data)
	{    	
		parent::assignData($data);

		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$catalogSession = $objectManager->create('\Magento\Catalog\Model\Session');

		if(isset($data)) {
			if(!empty($data->getData()['additional_data'])) {
				$catalogSession->setTokenValue($data->getData()['additional_data']['test1']);
			}
		}

        return $this;
    }

    // public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    // {

    //     $this->log('in_authorize');
    //     $this->log(json_encode($payment));
    //     $payment->setIsTransactionClosed(false);
    //     if (!$this->canAuthorize()) {
    //         throw new \Magento\Framework\Exception\LocalizedException(__('The authorize action is not available.'));
    //     }
    //     return $this;
    // }

    public function void(\Magento\Payment\Model\InfoInterface $payment)
    {
        $this->log('in_void');
        $order = $payment->getOrder();
        $orderid = $order->getRealOrderId();
        $this->log($orderid);

        $amount = $order->getGrandTotal();

        $status = $this->inquiry($orderid);
		
		if (is_null($status))
		{
			$this->log('in_void inquiry object is null / empty.');
			throw new \Magento\Framework\Validator\Exception(__('Cannot query payment status.'));
		}
		
		$res = null;
        if($status->status == 'A'){
            $res = $this->c2p_void($orderid);
        }else if($status->status == 'S'){
            $res = $this->c2p_refund($orderid, $amount);
		}else if($status->status == 'V'){
			$this->log('in_void: payment was already voided.');
			return $this;
        }else{
			throw new \Magento\Framework\Validator\Exception(__('Payment status ('.$status->status.') is not valid to perform void/refund. Try again later.'));
		}
		
		if (is_null($res))
		{
			$this->log('in_void res object is null.');
			throw new \Magento\Framework\Validator\Exception(__('Cannot perform void payment.'));
		}
		
		$this->log('in_void respCode=' . $res->respCode .', status='.$res->status);
        if($res->respCode == "00" 
			|| $res->status == "V") {
           return $this; 
        } else {
            throw new \Magento\Framework\Validator\Exception(__('Cannot perform void payment. reason_code:' . $res->respCode));
        }
    }

    public function cancel(\Magento\Payment\Model\InfoInterface $payment)
    {
        $this->log('in_cancel');
        $order = $payment->getOrder();
        $orderid = $order->getRealOrderId();
        $this->log($orderid);


        $amount = $order->getGrandTotal();

        $status = $this->inquiry($orderid);
		
		if (is_null($status))
		{
			$this->log('in_void inquiry object is null / empty.');
			throw new \Magento\Framework\Validator\Exception(__('Cannot query payment status.'));
		}
		
		$res = null;
        if($status->status == 'A'){
            $res = $this->c2p_void($orderid);
        }else if($status->status == 'S'){
            $res = $this->c2p_refund($orderid, $amount);
		}else if($status->status == 'V'){
			$this->log('in_void: payment was already voided.');
			return $this;
        }else{
			throw new \Magento\Framework\Validator\Exception(__('Payment status ('.$status->status.') is not valid to perform void/refund. Try again later.'));
		}
		
		if (is_null($res))
		{
			$this->log('in_void res object is null.');
			throw new \Magento\Framework\Validator\Exception(__('Cannot perform cancel payment.'));
		}
		
		$this->log('in_void respCode=' . $res->respCode .', status='.$res->status);
        if($res->respCode == "00" 
			|| $res->status == "V") {
           return $this; 
        } else {
            throw new \Magento\Framework\Validator\Exception(__('Cannot perform cancel payment. reason_code:' . $res->respCode));
        }
    }

    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {

        $this->log('in_capture');
        $order = $payment->getOrder();
        $orderid = $order->getRealOrderId();
        $amount = $order->getGrandTotal();

        $this->log($amount);

        $res = $this->c2p_capture($orderid, $amount);

		if (is_null($res))
		{
			throw new \Magento\Framework\Validator\Exception(__('Cannot perform capture.'));
		}
		
        if($res->respCode == "00" 
			|| $res->respCode == "32"  // Settlement/Capture not required
			|| $res->status == "S"
			|| $res->status == "SS"
			|| $res->status == "RS")
		{
            $order = $payment->getOrder();
            $billing = $order->getBillingAddress();
            $payment->setTransactionId($orderid)->setIsTransactionClosed(true);
            return $this;
        }else{
			throw new \Magento\Framework\Validator\Exception(__('Payment capture error. reason_code:' . $res->respCode));
		}


        
        /*
        try{
            $charge = \Stripe\Charge::create(array(
                'amount'    => $amount*100,
                'currency'  => strtolower($order->getBaseCurrencyCode()),
                'card'      => array(
                    'number'            =>  $payment->getCcNumber(),
                    'exp_month'         =>  sprintf('%02d',$payment->getCcExpMonth()),
                    'exp_year'          =>  $payment->getCcExpYear(),
                    'cvc'               =>  $payment->getCcCid(),
                    'name'              =>  $billing->getName(),
                    'address_line1'     =>  $billing->getStreet(1),
                    'address_line2'     =>  $billing->getStreet(2),
                    'address_zip'       =>  $billing->getPostcode(),
                    'address_state'     =>  $billing->getRegion(),
                    'address_country'   =>  $billing->getCountry(),
                ),
                'description'   =>  sprintf('#%s, %s', $order->getIncrementId(), $order->getCustomerEmail())
            ));
           
            $payment->setTransactionId('1')->setIsTransactionClosed(true);
            return $this;
 
        }catch (\Exception $e){
            $this->debugData(['exception' => $e->getMessage()]);
            throw new \Magento\Framework\Validator\Exception(__('Payment capturing error.'));
        }
        */
        
    }
 
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {

        $this->log('in_refund');
        $order = $payment->getOrder();
        $orderid = $order->getRealOrderId();
        $this->log($orderid);


        $amount = $order->getGrandTotal();
        $status = $this->inquiry($orderid);
		
		if (is_null($status))
		{
			$this->log('in_void inquiry object is null / empty.');
			throw new \Magento\Framework\Validator\Exception(__('Cannot query payment status.'));
		}
		
		$res = null;
        if($status->status == 'A'){
            $res = $this->c2p_void($orderid);
        }else if($status->status == 'S'){
            $res = $this->c2p_refund($orderid, $amount);
		}else if($status->status == 'V'){
			$this->log('in_void: payment was already voided.');
			return $this;
        }else{
			throw new \Magento\Framework\Validator\Exception(__('Payment status ('.$status->status.') is not valid to perform void/refund. Try again later.'));
		}
		
		if (is_null($res))
		{
			throw new \Magento\Framework\Validator\Exception(__('Cannot perform refund.'));
		}
        
        if($res->respCode == "00" 
			|| $res->status == "V") {
            $transactionId = $payment->getParentTransactionId();
            $payment
            ->setTransactionId($transactionId . '-' . \Magento\Sales\Model\Order\Payment\Transaction::TYPE_REFUND)
            ->setParentTransactionId($transactionId)
            ->setIsTransactionClosed(1)
            ->setShouldCloseParentTransaction(1);
           return $this; 
        }else
		{
            throw new \Magento\Framework\Validator\Exception(__('Cannot perform refund. reason_code:' . $res->respCode));
        }

        /*
        $this->log(json_encode($payment));
        $transactionId = $payment->getParentTransactionId();
 
        try {
            \Stripe\Charge::retrieve($transactionId)->refund();
        } catch (\Exception $e) {
            $this->debugData(['exception' => $e->getMessage()]);
            throw new \Magento\Framework\Validator\Exception(__('Payment refunding error.'));
        }
 
        $payment
            ->setTransactionId($transactionId . '-' . \Magento\Sales\Model\Order\Payment\Transaction::TYPE_REFUND)
            ->setParentTransactionId($transactionId)
            ->setIsTransactionClosed(1)
            ->setShouldCloseParentTransaction(1);
        */
    }

    /**
     * Instantiate state and set it to state object
     * @param string $paymentAction
     * @param Varien_Object
     */
    public function initialize($paymentAction, $stateObject)
    {        
        $stateObject->setState("Pending_2C2P");
        $stateObject->setStatus("Pending_2C2P");
        $stateObject->setIsNotified(false); 
    }


    // 2c2p helper functions //////////////////////

    public function inquiry($invoiceNo){

        $responsePayment = $this->inq_paymentProcess($invoiceNo);
        $response = base64_decode($responsePayment);


        //Validate response Hash
        $resXml=simplexml_load_string($response); 
        $res_version = $resXml->version;
        $res_respCode = $resXml->respCode;
        $res_processType = $resXml->processType;
        $res_invoiceNo = $resXml->invoiceNo;
        $res_amount = $resXml->amount;
        $res_status = $resXml->status;
        $res_approvalCode = $resXml->approvalCode;
        $res_referenceNo = $resXml->referenceNo;
        $res_transactionDateTime = $resXml->transactionDateTime;
        $res_paidAgent = $resXml->paidAgent;
        $res_paidChannel = $resXml->paidChannel;
        $res_maskedPan = $resXml->maskedPan;
        $res_eci = $resXml->eci;
        $res_paymentScheme = $resXml->paymentScheme;
        $res_processBy = $resXml->processBy;
        $res_refundReferenceNo = $resXml->refundReferenceNo;
        $res_userDefined1 = $resXml->userDefined1;
        $res_userDefined2 = $resXml->userDefined2;
        $res_userDefined3 = $resXml->userDefined3;
        $res_userDefined4 = $resXml->userDefined4;
        $res_userDefined5 = $resXml->userDefined5;    
        
        $res_stringToHash = $res_version.$res_respCode.$res_processType.$res_invoiceNo.$res_amount.$res_status.$res_approvalCode.$res_referenceNo.$res_transactionDateTime.$res_paidAgent.$res_paidChannel.$res_maskedPan.$res_eci.$res_paymentScheme.$res_processBy.$res_refundReferenceNo.$res_userDefined1.$res_userDefined2.$res_userDefined3.$res_userDefined4.$res_userDefined5 ;
        $this->log('res_stringToHash : ' . $res_stringToHash);
		
		$res_responseHash = strtoupper(hash_hmac('sha1',$res_stringToHash,$this->secretKey, false)); 
		$this->log('Generated Hash : ' . $res_responseHash);
		

        if($resXml->hashValue == strtolower($res_responseHash)){
            $this->log('signature : OK, res_status=' . $res_status);
            return $resXml;
        }else{
			$this->log('signature : Mismatched. RESPONSE='.$resXml->hashValue .', SYSTEM='.$res_responseHash);
            return null;
        }
    }


    public function c2p_capture($invoiceNo, $amount){
        $this->log('in_c2p_capture');

        $responsePayment = $this->cap_paymentProcess($invoiceNo,$amount);
        $response =  base64_decode($responsePayment);

        //Validate response Hash
        $resXml=simplexml_load_string($response); 
        $res_version = $resXml->version;
        $res_respCode = $resXml->respCode;
        $res_processType = $resXml->processType;
        $res_invoiceNo = $resXml->invoiceNo;
        $res_amount = $resXml->amount;
        $res_status = $resXml->status;
        $res_approvalCode = $resXml->approvalCode;
        $res_referenceNo = $resXml->referenceNo;
        $res_transactionDateTime = $resXml->transactionDateTime;
        $res_paidAgent = $resXml->paidAgent;
        $res_paidChannel = $resXml->paidChannel;
        $res_maskedPan = $resXml->maskedPan;
        $res_eci = $resXml->eci;
        $res_paymentScheme = $resXml->paymentScheme;
        $res_processBy = $resXml->processBy;
        $res_refundReferenceNo = $resXml->refundReferenceNo;
        $res_userDefined1 = $resXml->userDefined1;
        $res_userDefined2 = $resXml->userDefined2;
        $res_userDefined3 = $resXml->userDefined3;
        $res_userDefined4 = $resXml->userDefined4;
        $res_userDefined5 = $resXml->userDefined5;    
        
        $res_stringToHash = $res_version.$res_respCode.$res_processType.$res_invoiceNo.$res_amount.$res_status.$res_approvalCode.$res_referenceNo.$res_transactionDateTime.$res_paidAgent.$res_paidChannel.$res_maskedPan.$res_eci.$res_paymentScheme.$res_processBy.$res_refundReferenceNo.$res_userDefined1.$res_userDefined2.$res_userDefined3.$res_userDefined4.$res_userDefined5 ;
        $this->log('res_stringToHash : ' . $res_stringToHash);
		
		$res_responseHash = strtoupper(hash_hmac('sha1',$res_stringToHash,$this->secretKey, false)); 
		$this->log('Generated Hash : ' . $res_responseHash);
		
		
        if(strtolower($resXml->hashValue) == strtolower($res_responseHash)){
			$this->log('signature : OK, res_respCode=' . $res_respCode);
            return $resXml;
        }else{
			$this->log('signature : Mismatched. RESPONSE='.$resXml->hashValue .', SYSTEM='.$res_responseHash);
			return null;
		}

    }


    public function c2p_refund($invoiceNo, $amount){

        $responsePayment = $this->ref_paymentProcess($invoiceNo,$amount);
        $response =  base64_decode($responsePayment);

        //Validate response Hash
        $resXml=simplexml_load_string($response); 
        $res_version = $resXml->version;
        $res_respCode = $resXml->respCode;
        $res_processType = $resXml->processType;
        $res_invoiceNo = $resXml->invoiceNo;
        $res_amount = $resXml->amount;
        $res_status = $resXml->status;
        $res_approvalCode = $resXml->approvalCode;
        $res_referenceNo = $resXml->referenceNo;
        $res_transactionDateTime = $resXml->transactionDateTime;
        $res_paidAgent = $resXml->paidAgent;
        $res_paidChannel = $resXml->paidChannel;
        $res_maskedPan = $resXml->maskedPan;
        $res_eci = $resXml->eci;
        $res_paymentScheme = $resXml->paymentScheme;
        $res_processBy = $resXml->processBy;
        $res_refundReferenceNo = $resXml->refundReferenceNo;
        $res_userDefined1 = $resXml->userDefined1;
        $res_userDefined2 = $resXml->userDefined2;
        $res_userDefined3 = $resXml->userDefined3;
        $res_userDefined4 = $resXml->userDefined4;
        $res_userDefined5 = $resXml->userDefined5;    
        
		
		
        $res_stringToHash = $res_version.$res_respCode.$res_processType.$res_invoiceNo.$res_amount.$res_status.$res_approvalCode.$res_referenceNo.$res_transactionDateTime.$res_paidAgent.$res_paidChannel.$res_maskedPan.$res_eci.$res_paymentScheme.$res_processBy.$res_refundReferenceNo.$res_userDefined1.$res_userDefined2.$res_userDefined3.$res_userDefined4.$res_userDefined5 ;
        $this->log('res_stringToHash : ' . $res_stringToHash);
		
		$res_responseHash = strtoupper(hash_hmac('sha1',$res_stringToHash,$this->secretKey, false)); 
		$this->log('Generated Hash : ' . $res_responseHash);
		
		
        if(strtolower($resXml->hashValue) == strtolower($res_responseHash)){
			$this->log('signature : OK, res_respCode=' . $res_respCode);
            return $resXml;
        }else{
			$this->log('signature : Mismatched. RESPONSE='.$resXml->hashValue .', SYSTEM='.$res_responseHash);
			return null;
		}

    }


    public function c2p_void($invoiceNo){
         $responsePayment = $this->vod_paymentProcess($invoiceNo);
        $response = base64_decode($responsePayment);

        //Validate response Hash
        $resXml=simplexml_load_string($response); 
        $res_version = $resXml->version;
        $res_respCode = $resXml->respCode;
        $res_processType = $resXml->processType;
        $res_invoiceNo = $resXml->invoiceNo;
        $res_amount = $resXml->amount;
        $res_status = $resXml->status;
        $res_approvalCode = $resXml->approvalCode;
        $res_referenceNo = $resXml->referenceNo;
        $res_transactionDateTime = $resXml->transactionDateTime;
        $res_paidAgent = $resXml->paidAgent;
        $res_paidChannel = $resXml->paidChannel;
        $res_maskedPan = $resXml->maskedPan;
        $res_eci = $resXml->eci;
        $res_paymentScheme = $resXml->paymentScheme;
        $res_processBy = $resXml->processBy;
        $res_refundReferenceNo = $resXml->refundReferenceNo;
        $res_userDefined1 = $resXml->userDefined1;
        $res_userDefined2 = $resXml->userDefined2;
        $res_userDefined3 = $resXml->userDefined3;
        $res_userDefined4 = $resXml->userDefined4;
        $res_userDefined5 = $resXml->userDefined5;    
        
		
		
        $res_stringToHash = $res_version.$res_respCode.$res_processType.$res_invoiceNo.$res_amount.$res_status.$res_approvalCode.$res_referenceNo.$res_transactionDateTime.$res_paidAgent.$res_paidChannel.$res_maskedPan.$res_eci.$res_paymentScheme.$res_processBy.$res_refundReferenceNo.$res_userDefined1.$res_userDefined2.$res_userDefined3.$res_userDefined4.$res_userDefined5 ;
        $this->log('res_stringToHash : ' . $res_stringToHash);
		
		$res_responseHash = strtoupper(hash_hmac('sha1',$res_stringToHash,$this->secretKey, false)); 
		$this->log('Generated Hash : ' . $res_responseHash);
		
		
        if(strtolower($resXml->hashValue) == strtolower($res_responseHash)){
			$this->log('signature : OK, res_respCode=' . $res_respCode);
            return $resXml;
        }else{
			$this->log('signature : Mismatched. RESPONSE='.$resXml->hashValue .', SYSTEM='.$res_responseHash);
			return null;
		}
    }



    function getPaymentGetwayRedirectUrl() {

        if ($this->objConfigSettings['mode']) {
            return 'https://demo2.2c2p.com/2C2PFrontend/PaymentActionV2/PaymentProcess.aspx';
        } else {        
            return 'https://t.2c2p.com/PaymentActionV2/PaymentProcess.aspx';
        }
    }


    public function loadsettings(){
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $configSettings = $objectManager->create('Magento\Framework\App\Config\ScopeConfigInterface');
        $this->objConfigSettings = $configSettings->getValue('payment/p2c2ppayment');
    }


    //inquiry
    public function inq_setTheParameters($invoiceNo){


        $this->merchant_id = $this->objConfigSettings['merchantId'];
        $this->secretKey = $this->objConfigSettings['secretKey'];
        $this->processType = "I";
        $this->invoiceNo = $invoiceNo;
        $this->version = "3.4";
        $stringToHash = $this->version . $this->merchant_id . $this->processType . $this->invoiceNo;
        $this->hash = strtoupper(hash_hmac('sha1', $stringToHash ,$this->secretKey , false));   //Compute hash value
    }

    public function inq_paymentProcess($invoiceNo){

        $this->loadsettings();

        //set up the parameters
        $this->inq_setTheParameters($invoiceNo);

        //Construct request message
        $xml = "<PaymentProcessRequest>
                <version>{$this->version}</version> 
                <merchantID>{$this->merchant_id}</merchantID>
                <processType>{$this->processType}</processType>
                <invoiceNo>{$this->invoiceNo}</invoiceNo> 
                <hashValue>{$this->hash}</hashValue>
                </PaymentProcessRequest>";  

        $this->log('request data : ' . $xml);
        $payload = base64_encode($xml);//Encrypt payload

        $http = new \P2c2p\P2c2pPayment\Helper\HTTP();
        $response = $http->post($this->getPaymentGetwayRedirectUrl(),"paymentRequest=".$payload);
		$this->log('response data : ' . base64_decode($response));

        return $response;
    }

    /////////////

    public function cap_setTheParameters($invoiceNo,$amount){

        $this->merchant_id = $this->objConfigSettings['merchantId'];
        $this->secretKey = $this->objConfigSettings['secretKey'];
        $this->processType = "S";
        $this->invoiceNo =$invoiceNo;
        $this->amount = $amount;
        $this->version = "3.4";
        $stringToHash = $this->version . $this->merchant_id . $this->processType . $this->invoiceNo. $this->amount;;
        $this->hash = strtoupper(hash_hmac('sha1', $stringToHash ,$this->secretKey , false));   //Compute hash value
    }

    public function cap_paymentProcess($invoiceNo,$amount){
        

        $this->loadsettings();

        //set up the parameters
        $this->cap_setTheParameters($invoiceNo,$amount);

        //Construct request message
        $xml = "<PaymentProcessRequest>
                <version>{$this->version}</version> 
                <merchantID>{$this->merchant_id}</merchantID>
                <processType>{$this->processType}</processType>
                <invoiceNo>{$this->invoiceNo}</invoiceNo> 
                <actionAmount>{$amount}</actionAmount>
                <hashValue>{$this->hash}</hashValue>
                </PaymentProcessRequest>";  
		$this->log('request data : ' . $xml);
        $payload = base64_encode($xml);//Encrypt payload

        $http = new \P2c2p\P2c2pPayment\Helper\HTTP();
        $response = $http->post($this->getPaymentGetwayRedirectUrl(),"paymentRequest=".$payload);
		$this->log('response data : ' . base64_decode($response));
        return $response;
    }


    public function ref_setTheParameters($invoiceNo,$amount){

        $this->merchant_id = $this->objConfigSettings['merchantId'];
        $this->secretKey = $this->objConfigSettings['secretKey'];
        $this->processType = "R";
        $this->invoiceNo =$invoiceNo;
        $this->amount = $amount;
        $this->version = "3.4";
        $stringToHash = $this->version . $this->merchant_id . $this->processType . $this->invoiceNo . $this->amount;
        $this->hash = strtoupper(hash_hmac('sha1', $stringToHash ,$this->secretKey , false));   //Compute hash value
    }

    public function ref_paymentProcess($invoiceNo,$amount){

        $this->loadsettings();

        //set up the parameters
        $this->ref_setTheParameters($invoiceNo,$amount);

        //Construct request message
        $xml = "<PaymentProcessRequest>
                <version>{$this->version}</version> 
                <merchantID>{$this->merchant_id}</merchantID>
                <processType>{$this->processType}</processType>
                <invoiceNo>{$this->invoiceNo}</invoiceNo> 
                <actionAmount>{$amount}</actionAmount>
                <hashValue>{$this->hash}</hashValue>
                </PaymentProcessRequest>";  

        $this->log('request data : ' . $xml);
        $payload = base64_encode($xml);//Encrypt payload

        $http = new \P2c2p\P2c2pPayment\Helper\HTTP();
        $response = $http->post($this->getPaymentGetwayRedirectUrl(),"paymentRequest=".$payload);
		$this->log('response data : ' . base64_decode($response));

        return $response;
    }


    public function vod_setTheParameters($invoiceNo){

        $this->merchant_id = $this->objConfigSettings['merchantId'];
        $this->secretKey = $this->objConfigSettings['secretKey'];
        $this->processType = "V";
        $this->invoiceNo = $invoiceNo;
        $this->version = "3.4";
        $stringToHash = $this->version . $this->merchant_id . $this->processType . $this->invoiceNo;
        $this->hash = strtoupper(hash_hmac('sha1', $stringToHash ,$this->secretKey , false));   //Compute hash value
    }

    public function vod_paymentProcess($invoiceNo){

        $this->loadsettings();

        //set up the parameters
        $this->vod_setTheParameters($invoiceNo);

        //Construct request message
        $xml = "<PaymentProcessRequest>
                <version>{$this->version}</version> 
                <merchantID>{$this->merchant_id}</merchantID>
                <processType>{$this->processType}</processType>
                <invoiceNo>{$this->invoiceNo}</invoiceNo> 
                <hashValue>{$this->hash}</hashValue>
                </PaymentProcessRequest>";  

        $this->log('request data : ' . $xml);
        $payload = base64_encode($xml);//Encrypt payload

        $http = new \P2c2p\P2c2pPayment\Helper\HTTP();
        $response = $http->post($this->getPaymentGetwayRedirectUrl(),"paymentRequest=".$payload);
		$this->log('response data : ' . base64_decode($response));

        return $response;
    }
}