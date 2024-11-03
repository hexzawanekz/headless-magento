<?php

namespace StripeIntegration\Payments\Controller\Customer;

use Magento\Customer\Model\Session;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Result\PageFactory;
use StripeIntegration\Payments\Helper\Generic;
use StripeIntegration\Payments\Exception\PaymentMethodInUse;
use StripeIntegration\Payments\Exception\InvalidPaymentMethod;

class PaymentMethods implements ActionInterface
{
    private $resultFactory;
    private $resultPageFactory;
    private $helper;
    private $stripeCustomer;
    private $customerSession;
    private $request;

    public function __construct(
        PageFactory $resultPageFactory,
        Session $session,
        Generic $helper,
        ResultFactory $resultFactory,
        RequestInterface $request
    )
    {
        $this->resultPageFactory = $resultPageFactory;
        $this->helper = $helper;
        $this->stripeCustomer = $helper->getCustomerModel();
        $this->customerSession = $session;
        $this->resultFactory = $resultFactory;
        $this->request = $request;
    }

    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->redirect('customer/account/login');
        }

        $params = $this->request->getParams();

        if (isset($params['delete']))
            return $this->delete($params['delete'], $this->request->getParam("fingerprint", null));
        else if (isset($params['redirect_status']))
            return $this->outcome($params['redirect_status'], $params);

        return $this->resultPageFactory->create();
    }

    public function outcome($code, $params)
    {
        if ($code == "succeeded")
            $this->helper->addSuccess(__("The payment method has been successfully added."));

        return $this->redirect('stripe/customer/paymentmethods');
    }

    public function delete($token, $fingerprint = null)
    {
        try
        {
            $card = $this->stripeCustomer->deletePaymentMethod($token, $fingerprint);

            // In case we deleted a source
            if (isset($card->card))
                $card = $card->card;

            if (!empty($card->last4))
                $this->helper->addSuccess(__("Card •••• %1 has been deleted.", $card->last4));
            else
                $this->helper->addSuccess(__("The payment method has been deleted."));
        }
        catch (PaymentMethodInUse|InvalidPaymentMethod $e)
        {
            $this->helper->addError($e->getMessage());
            return $this->redirect('stripe/customer/paymentmethods');
        }
        catch (\Stripe\Exception\CardException $e)
        {
            $this->helper->addError($e->getMessage());
        }
        catch (\Exception $e)
        {
            $this->helper->addError($e->getMessage());
            $this->helper->logError($e->getMessage());
            $this->helper->logError($e->getTraceAsString());
        }

        return $this->redirect('stripe/customer/paymentmethods');
    }

    public function redirect($url)
    {
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $redirect->setPath($url);

        return $redirect;
    }
}
