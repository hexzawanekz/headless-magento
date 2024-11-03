<?php

/*
 * Created by 2C2P
 * Date 20 June 2017
 * AbstractCheckoutRedirectAction is used for intermediate for request and reponse.
 */

namespace P2c2p\P2c2pPayment\Controller;

use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\OrderFactory;
use Magento\Catalog\Model\Session as catalogSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Customer\Model\Session as Customer;
use P2c2p\P2c2pPayment\Controller\AbstractCheckoutAction;
use P2c2p\P2c2pPayment\Helper\Checkout;
use P2c2p\P2c2pPayment\Helper\P2c2pRequest;
use P2c2p\P2c2pPayment\Helper\P2c2pMeta;
use P2c2p\P2c2pPayment\Helper\P2c2pHash;

abstract class AbstractCheckoutRedirectAction extends AbstractCheckoutAction
{
    protected $objCheckoutHelper, $objCustomer;
    protected $objP2c2pRequestHelper, $objP2c2pMetaHelper;
    protected $objP2c2pHashHelper, $objConfigSettings;
    protected $objCatalogSession;

    public function __construct(Context $context,
            Session $checkoutSession, OrderFactory $orderFactory,
            Customer $customer, Checkout $checkoutHelper,
            P2c2pRequest $p2c2pRequest, P2c2pMeta $p2c2pMeta,
            P2c2pHash $p2c2pHash, ScopeConfigInterface $configSettings ,
            catalogSession $catalogSession) {

        parent::__construct($context, $checkoutSession, $orderFactory);
        $this->objCheckoutHelper = $checkoutHelper;
        $this->objCustomer = $customer;
        $this->objP2c2pRequestHelper = $p2c2pRequest;
        $this->objP2c2pMetaHelper = $p2c2pMeta;
        $this->objP2c2pHashHelper = $p2c2pHash;
        $this->objConfigSettings = $configSettings->getValue('payment/p2c2ppayment');
        $this->objCatalogSession = $catalogSession;        
    }

    //This object is hold the custom filed data for payment method like selected store Card's, other setting, etc.
    protected function getCatalogSession() {
        return $this->objCatalogSession;
    }

    //Get the Magento configuration setting object that hold global setting for Merchant configuration
    protected function getConfigSettings() {
        return $this->objConfigSettings;
    }

    //Get the P2c2p plugin Hash helper class object to check hash value is valid or not. Also generate the hash for any request.
    protected function getHashHelper() {
        return $this->objP2c2pHashHelper;
    }

    //Get the Meta helper object. It is responsible for storing the data into database. like p2c2p_meta, p2c2p_token table.
    protected function getMetaDataHelper() {
        return $this->objP2c2pMetaHelper;
    }

    //Get the p2c2p request helper class. It is responsible for construct the current user request for 2c2p Payment Gateway.
    protected function getP2c2pRequest($paramter,$isloggedIn) {
        return $this->objP2c2pRequestHelper->p2c2p_construct_request($paramter,$isloggedIn);
    }

    //This is magento object to get the customer object.
    protected function getCustomerSession() {
        return $this->objCustomer;
    }

    //Get the P2c2p cehckout object. It is reponsible for hold the current users cart detail's
    protected function getCheckoutHelper() {
        return $this->objCheckoutHelper;
    }

    //This function is used to redirect to customer message action method after make successfully payment / 123 payment type.
    protected function executeSuccessAction($request){
        if ($this->getCheckoutSession()->getLastRealOrderId()) {
            $this->_forward('Success','Payment','p2c2p', $request);
        }
    }
    
    //This function is redirect to cart after customer is cancel the payment.
    protected function executeCancelAction(){
        $this->getCheckoutHelper()->cancelCurrentOrder('');
        $this->getCheckoutHelper()->restoreQuote();
        $this->redirectToCheckoutCart();
    }    
}