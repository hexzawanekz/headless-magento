<?php

/*
 * Created by 2C2P
 * Date 20 June 2017
 * Payment Success Controller action method is used to display the payment success response into Magento frontend.
 */

namespace P2c2p\P2c2pPayment\Controller\Payment;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Success extends \Magento\Framework\App\Action\Action 
{
	protected $resultPageFactory;

	public function __construct(Context $context, PageFactory $resultPageFactory) {
		$this->resultPageFactory = $resultPageFactory;
		parent::__construct($context);
	}
	
	public function execute() {
		return $this->resultPageFactory->create();
	}
}