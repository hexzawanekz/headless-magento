<?php

namespace StripeIntegration\Tax\Model\StripeTax\Request;

use StripeIntegration\Tax\Helper\Tax;

class ShippingCost
{
    public const AMOUNT_KEY = 'amount';
    public const SHIPPING_RATE_KEY = 'shipping_rate';
    public const TAX_BEHAVIOR_KEY = 'tax_behavior';
    public const TAX_CODE_KEY = 'tax_code';

    private $amount;
    private $shippingRate;
    private $taxBehavior;
    private $taxCode;

    private $shippingCostHelper;
    private $taxHelper;

    public function __construct(
        \StripeIntegration\Tax\Helper\ShippingCost $shippingCostHelper,
        Tax $taxHelper
    )
    {
        $this->shippingCostHelper = $shippingCostHelper;
        $this->taxHelper = $taxHelper;
    }

    public function formData($total, $currency)
    {
        $shippingCost = $total->getShippingTaxCalculationAmount() - $total->getShippingDiscountAmount();
        $this->setData($shippingCost, $currency);
    }

    public function formDataForInvoiceTax($order, $invoice)
    {
        $shippingCost = $this->shippingCostHelper->getShippingCostForInvoiceTax($order, $invoice);
        $currency = $order->getOrderCurrencyCode();
        $this->setData($shippingCost, $currency);
    }

    private function setData($shippingCost, $currency)
    {
        $this->setAmount($this->shippingCostHelper->getAmount($shippingCost, $currency));
        $this->setTaxBehavior($this->taxHelper->getShippingTaxBehavior());
        $this->setTaxCode($this->taxHelper->getShippingTaxCode());
    }

    public function toArray()
    {
        $shippingAmount = [];
        $shippingAmount[self::AMOUNT_KEY] = $this->getAmount();
        $shippingAmount[self::TAX_BEHAVIOR_KEY] = $this->getTaxBehavior();
        $shippingAmount[self::TAX_CODE_KEY] = $this->getTaxCode();

        return $shippingAmount;
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function setAmount($amount)
    {
        $this->amount = $amount;

        return $this;
    }

    public function getShippingRate()
    {
        return $this->shippingRate;
    }

    public function setShippingRate($shippingRate)
    {
        $this->shippingRate = $shippingRate;

        return $this;
    }

    public function getTaxBehavior()
    {
        return $this->taxBehavior;
    }

    public function setTaxBehavior($taxBehavior)
    {
        $this->taxBehavior = $taxBehavior;

        return $this;
    }

    public function getTaxCode()
    {
        return $this->taxCode;
    }

    public function setTaxCode($taxCode)
    {
        $this->taxCode = $taxCode;

        return $this;
    }


}