<?php

namespace StripeIntegration\Payments\Controller\Payment;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;

class Cancel implements ActionInterface
{
    private $checkoutSession;
    private $request;
    private $resultFactory;

    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        RequestInterface $request,
        ResultFactory $resultFactory
    )
    {
        $this->checkoutSession = $checkoutSession;
        $this->request = $request;
        $this->resultFactory = $resultFactory;
    }

    /**
     * @return ResultInterface
     */
    public function execute()
    {
        $paymentMethodType = $this->request->getParam('payment_method');
        $lastRealOrderId = $this->checkoutSession->getLastRealOrderId();

        switch ($paymentMethodType) {
            case 'stripe_checkout':
                $this->checkoutSession->restoreQuote();
                $this->checkoutSession->setLastRealOrderId($lastRealOrderId);
                return $this->redirect('checkout');
            default:
                return $this->redirect('checkout/cart');
        }
    }

    public function redirect($url, array $params = [])
    {
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $redirect->setPath($url, $params);

        return $redirect;
    }
}
