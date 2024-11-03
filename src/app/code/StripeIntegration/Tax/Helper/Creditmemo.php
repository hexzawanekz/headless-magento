<?php

namespace StripeIntegration\Tax\Helper;

class Creditmemo
{
    private $currencyHelper;

    public function __construct(
        Currency $currencyHelper
    )
    {
        $this->currencyHelper = $currencyHelper;
    }

    public function hasAdjustments($creditmemo)
    {
        if ($creditmemo->getAdjustmentPositive() != 0 || $creditmemo->getAdjustmentNegative() != 0) {
            return true;
        }

        return false;
    }

    public function hasShippingToRevert($creditmemo)
    {
        return $creditmemo->getShippingAmount() > 0 && $creditmemo->getShippingTaxAmount() > 0;
    }

    /**
     * Adds the amount which will be in the revert request.
     * This will help to stop looping through invoices or other items if the amount to be reverted reaches the
     * credit memo grand total.
     *
     * @param $creditmemo
     * @param $amount
     * @param $taxAmount
     * @param $taxExclusive
     * @return void
     */
    public function updateAmountToRevert($creditmemo, $amount, $taxAmount, $taxExclusive = true)
    {
        $creditmemo->setAmountToRevert(
            $creditmemo->getAmountToRevert() + $this->currencyHelper->stripeAmountToMagentoAmount(abs($amount), $creditmemo->getOrderCurrencyCode())
        );
        if ($taxExclusive) {
            $creditmemo->setAmountToRevert(
                $creditmemo->getAmountToRevert() + $this->currencyHelper->stripeAmountToMagentoAmount(abs($taxAmount), $creditmemo->getOrderCurrencyCode())
            );
        }
    }
}