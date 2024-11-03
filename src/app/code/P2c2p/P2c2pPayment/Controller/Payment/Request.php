<?php

/*
 * Created by 2C2P
 * Date 20 June 2017
 * Payment Request Controller is responsible for send request to 2c2p payment getaway.
 */

namespace P2c2p\P2c2pPayment\Controller\Payment;

class Request extends \P2c2p\P2c2pPayment\Controller\AbstractCheckoutRedirectAction
{	


	public function log($data){
		$writer = new \Zend\Log\Writer\Stream(BP . '/var/log/p2c2p.log');
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            $logger->info($data);
	}


	public function execute() {

		//Get current order detail from OrderFactory object.
		$orderId = $this->getCheckoutSession()->getLastRealOrderId();

		if(empty($orderId)) {
			die("Aunthentication Error: Order is is empty.");
		}

		$order = $this->getOrderDetailByOrderId($orderId);

		//Redirect to home page with error
		if(!isset($order)) {
			$this->_redirect('');
			return;
		}

		$objCatalogSessionHelper = $this->getCatalogSession();
		$tokenId = $objCatalogSessionHelper->getTokenValue();
		$strTokenKey = '';

		if(isset($tokenId)) {
			$objMetaHelper = $this->getMetaDataHelper();
			$strTokenKey = $objMetaHelper->getTokenByTokenId($tokenId)->getData('stored_card_unique_id');
		}
		
		
		$customerSession = $this->getCustomerSession();
		//Get the selected product name from the OrderFactory object.

		$item_count = count($order->getAllItems());
        $current_count = 0;
        $product_name = '';

        foreach($order->getAllItems() as $item) {

            $product_name .= $item->getName();
            $current_count++;

            if($item_count !== $current_count)
                $product_name .= ', ';
        }

        $product_name .= '.';
				

		//Check whether customer is logged in or not into current merchant website.
		if($customerSession->isLoggedIn()) {
			$cust_email = $customerSession->getCustomer()->getEmail();
		} else {
			$billingAddress = $order->getBillingAddress();
			$cust_email = $billingAddress->getEmail();
		}

		//baseurl 
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
		$baseurl = $storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
				
		//Create basic form array.
		$fun2c2p_args = array(
			'payment_description'   => substr($product_name,0,240),
			'order_id'              => $this->getCheckoutSession()->getLastRealOrderId(),
			'invoice_no'            => $this->getCheckoutSession()->getLastRealOrderId(),		
			'amount'                => round($order->getGrandTotal(),2),
			'customer_email'        => $cust_email,
			'stored_card_unique_id'	=> !empty($strTokenKey) ? $strTokenKey : '',
			'result_url_1' 			=> $baseurl.'p2c2p/payment/response'
			);

		//$this->log(json_encode($fun2c2p_args));
		$this->log('in_request');

		echo $this->getP2c2pRequest($fun2c2p_args,$customerSession->isLoggedIn());	
	}
}