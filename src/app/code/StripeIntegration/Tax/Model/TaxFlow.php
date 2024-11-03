<?php

namespace StripeIntegration\Tax\Model;

class TaxFlow
{
    public $orderTaxCalculationSuccessful = false;
    public $orderMappingIssues = false;
    public $orderItemCalculationIssues = false;
    public $invoiceTaxCalculationSuccessful = false;
    public $invoiceTransactionSuccessful = false;
    public $creditMemoTransactionSuccessful = false;

    public function canOrderProceed()
    {
        return $this->orderTaxCalculationSuccessful &&
            !$this->orderMappingIssues &&
            !$this->orderItemCalculationIssues;
    }

    public function canInvoiceProceed()
    {
        return $this->invoiceTaxCalculationSuccessful && $this->invoiceTransactionSuccessful;
    }

    public function canCreditMemoProceed()
    {
        return $this->creditMemoTransactionSuccessful;
    }
}