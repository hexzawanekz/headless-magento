<?php

namespace StripeIntegration\Tax\Model\Order\Transaction;

class Item
{
    private $quantity;
    private $unitPrice;
    private $discountAmount;
    private $stripeTotalCalculatedAmount;
    private $stripeTotalCalculatedTax;
    private $stripeCurrency;
    private $useBaseCurrency;
    private $priceIncludesTax;

    /**
     * @param $invoiceItem
     * @param $lineItem
     * @return $this
     *
     * Prepare a calculation item for getting the values related to items. This preparation is done so that we can
     * re-use the calculations from the quote calculations
     */
    public function prepare($invoiceItem, $lineItem)
    {
        $this->setCommonFields($lineItem)
            ->setQuantity($invoiceItem->getQty());

        if ($this->getPriceIncludesTax()) {
            $unitPrice = $invoiceItem->getPriceInclTax();
        } else {
            $unitPrice = $invoiceItem->getPrice();
        }

        return $this->setDiscountAmount($invoiceItem->getDiscountAmount())
            ->setUnitPrice($unitPrice);
    }

    /**
     * @param $invoice
     * @param $lineItem
     * @return $this
     *
     * Prepare a calculation item for getting the values related to shipping. This preparation is done so that we can
     * re-use the calculations from the quote calculations
     */
    public function prepareForShipping($invoice, $lineItem)
    {
        $this->setCommonFields($lineItem)
            ->setQuantity(1);

        if ($this->getPriceIncludesTax()) {
            $unitPrice = $invoice->getShippingInclTax();
        } else {
            $unitPrice = $invoice->getShippingAmount();
        }

        return $this->setUnitPrice($unitPrice)
            ->setDiscountAmount($invoice->getOrder()->getShippingDiscountAmount());
    }

    public function prepareItemGW($invoiceItem, $lineItem)
    {
        $this->setCommonFields($lineItem)
            ->setQuantity($invoiceItem->getQty())
            ->setDiscountAmount(0)
            ->setUnitPrice($invoiceItem->getOrderItem()->getGwPrice());
    }

    public function prepareSalesObjectGW($object, $lineItem)
    {
        $this->setCommonFields($lineItem)
            ->setQuantity(1)
            ->setDiscountAmount(0)
            ->setUnitPrice($object->getGwPrice());
    }

    public function preparePrintedCard($object, $lineItem)
    {
        $this->setCommonFields($lineItem)
            ->setQuantity(1)
            ->setDiscountAmount(0)
            ->setUnitPrice($object->getGwCardPrice());
    }

    /**
     * @param $invoiceItem
     * @return Item
     *
     * When we use the base currency for calculating, we need to change the price and discount amount to the
     * base values.
     */
    public function setBaseCurrencyPrices($invoiceItem)
    {
        if ($this->getPriceIncludesTax()) {
            $unitPrice = $invoiceItem->getBasePriceInclTax();
        } else {
            $unitPrice = $invoiceItem->getBasePrice();
        }

        return $this->setDiscountAmount($invoiceItem->getBaseDiscountAmount())
            ->setUnitPrice($unitPrice);
    }

    /**
     * @param $invoice
     * @return Item
     *
     * When we use the base currency for calculating, we need to change the price and discount amount to the
     * base values.
     */
    public function setBaseCurrencyPricesForShipping($invoice)
    {
        if ($this->getPriceIncludesTax()) {
            $unitPrice = $invoice->getBaseShippingInclTax();
        } else {
            $unitPrice = $invoice->getBaseShippingAmount();
        }

        return $this->setUnitPrice($unitPrice)
            ->setDiscountAmount($invoice->getOrder()->getBaseShippingDiscountAmount());
    }

    public function setItemGwBaseCurrencyPrices($invoiceItem)
    {
        return $this->setUnitPrice($invoiceItem->getOrderItem()->getGwBasePrice());
    }

    public function setSalesObjectGwBaseCurrencyPrices($object)
    {
        return $this->setUnitPrice($object->getGwBasePrice());
    }

    public function setPrintedCardBaseCurrencyPrices($object)
    {
        return $this->setUnitPrice($object->getGwCardBasePrice());
    }

    private function setCommonFields($lineItem)
    {
        $this->setUseBaseCurrency(false)
        ->setStripeTotalCalculatedAmount($lineItem['stripe_total_calculated_amount'])
        ->setStripeTotalCalculatedTax($lineItem['stripe_total_calculated_tax'])
        ->setStripeCurrency($lineItem['stripe_currency'])
        ->setPriceIncludesTax($lineItem['price_includes_tax']);

        return $this;
    }

    public function getQuantity()
    {
        return $this->quantity;
    }

    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getUnitPrice()
    {
        return $this->unitPrice;
    }

    public function setUnitPrice($unitPrice)
    {
        $this->unitPrice = $unitPrice;
        return $this;
    }

    public function getDiscountAmount()
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount($discountAmount)
    {
        $this->discountAmount = $discountAmount;
        return $this;
    }

    public function getStripeTotalCalculatedAmount()
    {
        return $this->stripeTotalCalculatedAmount;
    }

    public function setStripeTotalCalculatedAmount($stripeTotalCalculatedAmount)
    {
        $this->stripeTotalCalculatedAmount = $stripeTotalCalculatedAmount;
        return $this;
    }

    public function getStripeTotalCalculatedTax()
    {
        return $this->stripeTotalCalculatedTax;
    }

    public function setStripeTotalCalculatedTax($stripeTotalCalculatedTax)
    {
        $this->stripeTotalCalculatedTax = $stripeTotalCalculatedTax;
        return $this;
    }

    public function getStripeCurrency()
    {
        return $this->stripeCurrency;
    }

    public function setStripeCurrency($stripeCurrency)
    {
        $this->stripeCurrency = $stripeCurrency;
        return $this;
    }

    public function getUseBaseCurrency()
    {
        return $this->useBaseCurrency;
    }

    public function setUseBaseCurrency($useBaseCurrency)
    {
        $this->useBaseCurrency = $useBaseCurrency;
        return $this;
    }

    public function getPriceIncludesTax()
    {
        return $this->priceIncludesTax;
    }

    public function setPriceIncludesTax($PriceIncludesTax)
    {
        $this->priceIncludesTax = $PriceIncludesTax;
        return $this;
    }
}