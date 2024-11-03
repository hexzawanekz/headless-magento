<?php

namespace StripeIntegration\Payments\Observer;

use \Magento\Framework\Event\ObserverInterface;
use \Magento\Framework\Event\Observer;
use StripeIntegration\Payments\Model\InitialFee;

class AddInitialFeeToStripeQuoteCalculation implements ObserverInterface
{
    public function execute(Observer $observer)
    {
        $total = $observer->getTotal();
        $additionalFees = $observer->getAdditionalFeesContainer();

        if ($total->getInitialFeeAmount()) {
            $itemDetails = [
                'amount' => $total->getInitialFeeAmount(),
                'tax_class_id' => $total->getInitialFeeTaxClassId(),
                'code' => InitialFee::INITIAL_FEE_TYPE
            ];

            $additionalFees->addAdditionalFee($itemDetails);
        }
    }
}