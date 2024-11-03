<?php

namespace P2c2p\P2c2pPayment\Controller\Token;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use P2c2p\P2c2pPayment\Model\TokenFactory;

class Remove extends \Magento\Framework\App\Action\Action
{    
    protected $objTokenFactory;

    public function __construct(Context $context,TokenFactory $objTokenFactory)
    {
        parent::__construct($context);             
        $this->objTokenFactory  = $objTokenFactory;
    }

    public function execute()
    {
        if(!isset($_REQUEST['token'])) {
            echo "0"; die;
        }

        $objTokenFactoryModel = $this->objTokenFactory->create()->load($_REQUEST['token']);

        if(count($objTokenFactoryModel->getData()) > 0) {
            $objTokenFactoryModel->delete();    
            echo "1"; die;
        } else {
            echo "0"; die;
        }
    }
}