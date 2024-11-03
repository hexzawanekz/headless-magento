<?php

namespace StripeIntegration\Tax\Helper;

class ShippingCost
{
    private $currencyHelper;
    private $taxHelper;
    private $invoiceHelper;

    public function __construct(
        Currency $currencyHelper,
        Tax $taxHelper,
        Invoice $invoiceHelper
    )
    {
        $this->currencyHelper = $currencyHelper;
        $this->taxHelper = $taxHelper;
        $this->invoiceHelper = $invoiceHelper;
    }

    public function getAmount($amount, $currency)
    {
        return $this->currencyHelper->magentoAmountToStripeAmount($amount, $currency);
    }

    public function getShippingCostForInvoiceTax($order, $invoice)
    {
        $shippingCost = 0;
        if ($this->invoiceHelper->canIncludeShipping($invoice)) {
            $shippingCost = $order->getShippingAmount();
            if ($this->taxHelper->isShippingTaxInclusive()) {
                $shippingCost = $order->getShippingInclTax();
            }

            if ($order->getShippingDiscountAmount()) {
                $shippingCost -= $order->getShippingDiscountAmount();
            }
        }

        return $shippingCost;
    }

    public function getShippingCostForReversal($creditMemo)
    {
        $shippingCost = $creditMemo->getShippingAmount();
        if ($this->taxHelper->isShippingTaxInclusive()) {
            $shippingCost = $creditMemo->getShippingInclTax();
        }
        $stripeAmount = $this->getAmount($shippingCost, $creditMemo->getOrderCurrencyCode());

        return -$stripeAmount;
    }

    public function getShippingCostTaxForReversal($creditMemo)
    {
        $shippingTax = $creditMemo->getShippingTaxAmount();
        $stripeAmount = $this->getAmount($shippingTax, $creditMemo->getOrderCurrencyCode());

        return -$stripeAmount;
    }
}