<?php

namespace StripeIntegration\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;

class AddInitialFeeTaxToOrderObserver implements ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getData('order');
        $quote = $observer->getData('quote');

        $order->setInitialFeeTax($quote->getInitialFeeTax());
        $order->setBaseInitialFeeTax($quote->getBaseInitialFeeTax());

        return $this;
    }
}
