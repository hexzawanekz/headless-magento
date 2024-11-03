<?php

namespace StripeIntegration\Payments\Observer;

use Magento\Payment\Observer\AbstractDataAssignObserver;

class PaymentMethodActiveObserver extends AbstractDataAssignObserver
{
    private $paymentMethodHelper;
    private $quoteHelper;
    private $config;

    public function __construct(
        \StripeIntegration\Payments\Helper\PaymentMethod $paymentMethodHelper,
        \StripeIntegration\Payments\Helper\Quote $quoteHelper,
        \StripeIntegration\Payments\Model\Config $config
    )
    {
        $this->paymentMethodHelper = $paymentMethodHelper;
        $this->quoteHelper = $quoteHelper;
        $this->config = $config;
    }

    /**
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $quote = $observer->getEvent()->getQuote();

        if (!$this->config->isSubscriptionsEnabled())
        {
            return;
        }

        $result = $observer->getEvent()->getResult();
        $methodInstance = $observer->getEvent()->getMethodInstance();
        $code = $methodInstance->getCode();
        $isAvailable = $result->getData('is_available');

        if ($isAvailable && $this->quoteHelper->hasSubscriptions($quote))
        {
            if ($this->paymentMethodHelper->supportsSubscriptions($code))
            {
                return;
            }
            else
            {
                $result->setData('is_available', false);
            }
        }
    }
}
