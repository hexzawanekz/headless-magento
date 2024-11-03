<?php

namespace P2c2p\P2c2pPayment\Controller\Token;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use P2c2p\P2c2pPayment\Model\TokenFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Index extends \Magento\Framework\App\Action\Action
{    
    protected $objTokenFactory;
    protected $objConfigSettings;

    public function __construct(Context $context, TokenFactory $objTokenFactory, ScopeConfigInterface $configSettings) {
        
        parent::__construct($context);             
        $this->objTokenFactory    = $objTokenFactory;
        $this->objConfigSettings  = $configSettings->getValue('payment/p2c2ppayment');
    }

    public function execute() {

        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        if(!$this->objConfigSettings['storedCard']) {
            $resultJson->setData("0");
            return $resultJson;
        }

        if(!isset($_POST['userId']) || empty($_POST['userId']))
            return;

        $objTokenFactoryModel = $this->objTokenFactory->create();
        $tokenCollection =  $objTokenFactoryModel->getCollection()->addFieldToFilter('user_id',$_POST['userId']);

        if(count($tokenCollection) > 0) {
            $resultJson->setData($tokenCollection);
        } else {
            $resultJson->setData(count($tokenCollection));
        }
        
        return $resultJson;
    }
}