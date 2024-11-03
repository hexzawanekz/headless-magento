<?php

namespace StripeIntegration\Tax\Model\StripeTax\Request;

use StripeIntegration\Tax\Helper\GiftOptions;
use StripeIntegration\Tax\Helper\Tax;

class LineItem
{
    public const AMOUNT_KEY = 'amount';
    public const PRODUCT_KEY = 'product';
    public const QUANTITY_KEY = 'quantity';
    public const REFERENCE_KEY = 'reference';
    public const TAX_BEHAVIOR_KEY = 'tax_behavior';
    public const TAX_CODE_KEY = 'tax_code';

    private $amount;
    private $product;
    private $quantity;
    private $reference;
    private $taxBehavior;
    private $taxCode;

    private $lineItemsHelper;
    private $taxHelper;
    private $giftOptionsHelper;

    public function __construct(
        \StripeIntegration\Tax\Helper\LineItems $lineItemsHelper,
        Tax $taxHelper,
        GiftOptions $giftOptionsHelper
    )
    {
        $this->lineItemsHelper = $lineItemsHelper;
        $this->taxHelper = $taxHelper;
        $this->giftOptionsHelper = $giftOptionsHelper;
    }

    public function formData($item, $currency, $parentQty = 1)
    {
        $this->setTaxCode($this->lineItemsHelper->getTaxCode($item));
        $this->setAmount($this->lineItemsHelper->getAmount($item, $currency));
        $this->setReference($this->lineItemsHelper->getReference($item));
        $this->setQuantity((int)$item->getQty() * $parentQty);
        $this->setTaxBehavior($this->taxHelper->getProductAndPromotionTaxBehavior());

        return $this;
    }

    public function formItemGiftOptionsData($item, $currency)
    {
        $this->setGiftOptionsCommonFields();
        $this->setQuantity((int)$item->getQty());
        $this->setAmount($this->giftOptionsHelper->getItemGiftOptionsAmount($item, $currency) * $this->getQuantity());
        $this->setReference($this->giftOptionsHelper->getItemGiftOptionsReference($item));

        return $this;
    }

    public function formSalesObjectGiftOptionsData($object, $currency)
    {
        $this->setGiftOptionsCommonFields();
        $this->setAmount($this->giftOptionsHelper->getSalseObjectGiftOptionsAmount($object, $currency));
        $this->setReference($this->giftOptionsHelper->getSalesObjectGiftOptionsReference($object));

        return $this;
    }

    public function formSalesObjectPrintedCardData($object, $currency)
    {
        $this->setGiftOptionsCommonFields();
        $this->setAmount($this->giftOptionsHelper->getSalesObjectPrintedCardAmount($object, $currency));
        $this->setReference($this->giftOptionsHelper->getsalesObjectPrintedCardReference($object));

        return $this;
    }

    private function setGiftOptionsCommonFields()
    {
        $this->setTaxCode($this->giftOptionsHelper->getGiftOptionsTaxCode());
        $this->setQuantity(1);
        $this->setTaxBehavior($this->taxHelper->getProductAndPromotionTaxBehavior());

        return $this;
    }

    public function formDataForInvoiceTax($item, $order)
    {
        $this->setTaxCode($this->lineItemsHelper->getTaxCodeForInvoiceTax($item));
        $this->setAmount($this->lineItemsHelper->getAmount($item, $order->getOrderCurrencyCode()));
        $this->setReference($this->lineItemsHelper->getReferenceForInvoiceTax($item, $order));
        $this->setQuantity((int)$item->getQty());
        $this->setTaxBehavior($this->taxHelper->getProductAndPromotionTaxBehavior());

        return $this;
    }

    public function formItemGwDataForInvoiceTax($item, $order)
    {
        $this->setGiftOptionsCommonFields();
        $this->setQuantity((int)$item->getQty());
        $this->setAmount($this->giftOptionsHelper->getItemGiftOptionsAmount($item->getOrderItem(), $order->getOrderCurrencyCode()) * $this->getQuantity());
        $this->setReference($this->giftOptionsHelper->getItemGwReferenceForInvoiceTax($item, $order));

        return $this;
    }

    public function formAdditionalFeeItemData($item, $currency, $additionalFeeData, $parentQty = 1)
    {
        $this->setQuantity((int)$item->getQty() * $parentQty);
        $this->setTaxCode($this->lineItemsHelper->getTaxCodeByTaxClassId($additionalFeeData['tax_class_id']));
        $this->setAmount($this->lineItemsHelper->getStripeFormattedAmount($additionalFeeData['amount'], $currency));
        $this->setReference($this->lineItemsHelper->getItemAdditionalFeeReference($item, $additionalFeeData['code']));
        $this->setTaxBehavior($this->taxHelper->getProductAndPromotionTaxBehavior());

        return $this;
    }

    public function formAdditionalFeeSalesEntityData($entity, $currency, $additionalFeeData)
    {
        $this->setQuantity(1);
        $this->setTaxCode($this->lineItemsHelper->getTaxCodeByTaxClassId($additionalFeeData['tax_class_id']));
        $this->setAmount($this->lineItemsHelper->getStripeFormattedAmount($additionalFeeData['amount'], $currency));
        $this->setReference($this->lineItemsHelper->getSalesEntityAdditionalFeeReference($entity, $additionalFeeData['code']));
        $this->setTaxBehavior($this->taxHelper->getProductAndPromotionTaxBehavior());

        return $this;
    }

    public function formAdditionalFeeInvoiceItemData($item, $order, $additionalFeeData)
    {
        $this->setTaxCode($this->lineItemsHelper->getTaxCodeByTaxClassId($additionalFeeData['tax_class_id']));
        $this->setAmount($this->lineItemsHelper->getStripeFormattedAmount($additionalFeeData['amount'], $order->getOrderCurrencyCode()));
        $this->setReference($this->lineItemsHelper->getReferenceForInvoiceAdditionalFee($item, $order, $additionalFeeData['code']));
        $this->setQuantity((int)$item->getQty());
        $this->setTaxBehavior($this->taxHelper->getProductAndPromotionTaxBehavior());

        return $this;
    }

    public function toArray()
    {
        $lineItems = [];
        $lineItems[self::AMOUNT_KEY] = $this->getAmount();
        if ($this->getTaxCode()) {
            $lineItems[self::TAX_CODE_KEY] = $this->getTaxCode();
        }
        $lineItems[self::TAX_BEHAVIOR_KEY] = $this->getTaxBehavior();
        $lineItems[self::QUANTITY_KEY] = $this->getQuantity();
        $lineItems[self::REFERENCE_KEY] = $this->getReference();

        return $lineItems;
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

    public function getProduct()
    {
        return $this->product;
    }

    public function setProduct($product)
    {
        $this->product = $product;

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

    public function getReference()
    {
        return $this->reference;
    }

    public function setReference($reference)
    {
        $this->reference = $reference;

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