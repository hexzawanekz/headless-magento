<?php

namespace StripeIntegration\Payments\Observer;

use \Magento\Framework\Event\ObserverInterface;
use \Magento\Framework\Event\Observer;
use StripeIntegration\Payments\Model\InitialFee;

class AddInitialFeeToStripeRefundCalculation implements ObserverInterface
{

    public function execute(Observer $observer)
    {
        $creditMemo = $observer->getCreditmemo();
        $additionalFees = $observer->getAdditionalFeesContainer();

        if ($creditMemo->getInitialFee() || $creditMemo->getInitialFeeTax()) {
            $itemDetails = [
                'amount' => $creditMemo->getInitialFee(),
                'amount_tax' => $creditMemo->getInitialFeeTax(),
                'code' => InitialFee::INITIAL_FEE_TYPE
            ];

            $additionalFees->addAdditionalFee($itemDetails);
        }
    }
}