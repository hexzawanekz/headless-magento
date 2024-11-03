<?php

namespace StripeIntegration\Tax\Observer;

use \Magento\Framework\Event\ObserverInterface;
use \Magento\Framework\Event\Observer;
use StripeIntegration\Tax\Exceptions\OrderException;
use StripeIntegration\Tax\Model\StripeTax;
use StripeIntegration\Tax\Model\TaxFlow;

class OrderProceed implements ObserverInterface
{
    private $taxFlow;
    private $stripeTax;

    public function __construct(
        TaxFlow $taxFlow,
        StripeTax $stripeTax
    ) {
        $this->taxFlow = $taxFlow;
        $this->stripeTax = $stripeTax;
    }

    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        // Check only if Tax enabled and order is new
        if ($this->stripeTax->isEnabled() && !$order->getId()) {
            if (!$this->taxFlow->canOrderProceed()) {
                throw new OrderException(__('Tax could not be calculated.'));
            }
        }
    }
}