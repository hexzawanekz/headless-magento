<?php

namespace StripeIntegration\Tax\Helper;

class GiftOptions
{
    public const ITEM_GIFT_WRAPPING_OPTION_SUFFIX = 'item_gw';
    public const QUOTE_GIFT_WRAPPING_OPTION_SUFFIX = 'quote_gw';
    public const QUOTE_PRINTED_CARD_OPTION_SUFFIX = 'printed_card_gw';

    private $lineItemsHelper;
    private $configHelper;

    public function __construct(
        LineItems $lineItemsHelper,
        Config $configHelper
    )
    {
        $this->lineItemsHelper = $lineItemsHelper;
        $this->configHelper = $configHelper;
    }

    public function itemHasGiftOptions($item)
    {
        return $item->getGwId() && $item->getGwPrice();
    }

    public function salesObjectHasGiftOptions($object)
    {
        return $object->getGwId() && $object->getGwPrice();
    }

    public function salesObjectHasPrintedCard($object)
    {
        return $object->getGwAddCard() && $object->getGwCardPrice();
    }

    public function getGiftOptionsTaxCode()
    {
        $taxClassId =  $this->configHelper->getTaxConfigData('classes', 'wrapping_tax_class');

        return $this->lineItemsHelper->getTaxCodeByTaxClassId($taxClassId);
    }

    public function getItemGiftOptionsAmount($item, $currency)
    {
        return $this->lineItemsHelper->getStripeFormattedAmount($item->getGwPrice(), $currency);
    }

    public function getSalseObjectGiftOptionsAmount($object, $currency)
    {
        return $this->lineItemsHelper->getStripeFormattedAmount($object->getGwPrice(), $currency);
    }

    public function getSalesObjectPrintedCardAmount($object, $currency)
    {
        return $this->lineItemsHelper->getStripeFormattedAmount($object->getGwCardPrice(), $currency);
    }

    public function getItemGiftOptionsReference($item)
    {
        return $this->lineItemsHelper->getItemAdditionalFeeReference($item, self::ITEM_GIFT_WRAPPING_OPTION_SUFFIX);
    }

    public function getSalesObjectGiftOptionsReference($object)
    {
        return $this->lineItemsHelper->getSalesEntityAdditionalFeeReference($object, self::QUOTE_GIFT_WRAPPING_OPTION_SUFFIX);
    }

    public function getSalesObjectPrintedCardReference($object)
    {
        return $this->lineItemsHelper->getSalesEntityAdditionalFeeReference($object, self::QUOTE_PRINTED_CARD_OPTION_SUFFIX);
    }

    public function getItemGwReferenceForInvoiceTax($item, $order)
    {
        return $this->lineItemsHelper->getReferenceForInvoiceAdditionalFee($item, $order, self::ITEM_GIFT_WRAPPING_OPTION_SUFFIX);
    }

    public function invoiceHasGw($invoice)
    {
        return $invoice->getGwPrice() || $invoice->getGwTaxAmount();
    }

    public function invoiceHasPrintedCard($invoice)
    {
        return $invoice->getGwCardPrice() || $invoice->getGwCardTaxAmount();
    }
}