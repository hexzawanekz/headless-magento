<?php

namespace StripeIntegration\Tax\Model\StripeTransactionReversal\Request;

use StripeIntegration\Tax\Helper\Creditmemo;
use StripeIntegration\Tax\Helper\Tax;

class ShippingCost
{
    public const AMOUNT_FIELD_NAME = 'amount';
    public const AMOUNT_TAX_FIELD_NAME = 'amount_tax';

    private $amount;
    private $amountTax;
    private $shippingCostHelper;
    private $creditmemoHelper;
    private $taxHelper;

    public function __construct(
        \StripeIntegration\Tax\Helper\ShippingCost $shippingCostHelper,
        Creditmemo $creditmemoHelper,
        Tax $taxHelper
    )
    {
        $this->shippingCostHelper = $shippingCostHelper;
        $this->creditmemoHelper = $creditmemoHelper;
        $this->taxHelper = $taxHelper;
    }

    public function formOnlineData($creditMemo)
    {
        $this->amount = $this->shippingCostHelper->getShippingCostForReversal($creditMemo);
        $this->amountTax = $this->shippingCostHelper->getShippingCostTaxForReversal($creditMemo);

        return $this;
    }

    public function formOfflineData($creditMemo, $invoice, $transaction)
    {
        // If a transaction has shipping data on it, it is the transaction which contains all the shipping.
        // Magento functions so that if you have multiple invoices, the first invoice will contain the shipping for
        // the whole order.
        if ($transaction->hasShippingTax() && $this->creditmemoHelper->hasShippingToRevert($creditMemo)) {
            $this->amount = $this->shippingCostHelper->getShippingCostForReversal($creditMemo);
            $this->amountTax = $this->shippingCostHelper->getShippingCostTaxForReversal($creditMemo);
        } else {
            $this->amount = 0;
            $this->amountTax = 0;

            // If there is no shipping or if the credit memo does not have any shipping to revert,
            // consider the shipping reverted for the transaction
            return true;
        }

        // Add to the amount to be reverted for the credit memo.
        $this->creditmemoHelper->updateAmountToRevert($creditMemo, $this->amount, $this->amountTax, $this->taxHelper->isShippingTaxExclusive());

        // The amount formed will be negative. We will use this check to determine whether to refund the
        // transaction fully or not.
        if (($transaction->getShippingTaxAmount() + $this->amountTax) === 0 &&
            ($transaction->getShippingAmount() + $this->amount) === 0
        ) {
            return true;
        }

        return false;
    }

    public function toArray()
    {
        return [
            self::AMOUNT_FIELD_NAME => $this->amount,
            self::AMOUNT_TAX_FIELD_NAME => $this->amountTax,
        ];
    }

    public function canIncludeInRequest()
    {
        if ($this->amount || $this->amountTax) {
            return true;
        }

        return false;
    }
}