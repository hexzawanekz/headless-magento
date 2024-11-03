<?php

/*
 * Created by 2C2P
 * Date 20 June 2017
 * This block class is responsible for give the detail into sucess.phtml file.
 */

namespace P2c2p\P2c2pPayment\Block;

use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Order;
use Magento\Customer\Model\Session as customerSession;
use Magento\Directory\Model\Currency;
use Magento\Store\Model\StoreManagerInterface;

class Form extends \Magento\Framework\View\Element\Template
{
	protected $objOrder;
	protected $objCustomerSession;
	protected $objStoreManagerInterface;

	public function __construct(Context $context, Order $order, customerSession $customerSession, StoreManagerInterface $storeManagerInterface) {

		parent::__construct($context);
		$this->objOrder = $order;
		$this->objCustomerSession = $customerSession;
		$this->storeManagerInterface = $storeManagerInterface;
	}

	public function getResponseParams() {
		return $this->getRequest()->getParams();
	}

	public function getOrderDetails($orderId) {
		return $this->objOrder->loadByIncrementId($orderId);
	}

	public function getCustomerDetail() {
		return $this->objCustomerSession;
	}

	public function getBaseCurrencyCode() {
		return $this->storeManagerInterface->getStore()->getCurrentCurrency()->getCode();
	}
}