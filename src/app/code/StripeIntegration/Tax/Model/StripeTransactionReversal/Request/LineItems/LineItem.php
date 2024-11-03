<?php

namespace StripeIntegration\Tax\Model\StripeTransactionReversal\Request\LineItems;

use StripeIntegration\Tax\Helper\Tax;
use StripeIntegration\Tax\Helper\Creditmemo;
use StripeIntegration\Tax\Helper\GiftOptions;
use StripeIntegration\Tax\Helper\LineItems;

class LineItem
{
    public const AMOUNT_FIELD_NAME = 'amount';
    public const AMOUNT_TAX_FIELD_NAME = 'amount_tax';
    public const ORIGINAL_LINE_ITEM_FIELD_NAME = 'original_line_item';
    public const REFERENCE_FIELD_NAME = 'reference';
    public const QUANTITY_FIELD_NAME = 'quantity';

    private $amount;
    private $amountTax;
    private $originalLineItem;
    private $reference;
    private $lineItemsHelper;
    private $quantity = 1;
    private $giftOptionsHelper;
    private $creditmemoHelper;
    private $taxHelper;

    public function __construct(
        LineItems $lineItemsHelper,
        GiftOptions $giftOptionsHelper,
        Creditmemo $creditmemoHelper,
        Tax $taxHelper
    )
    {
        $this->lineItemsHelper = $lineItemsHelper;
        $this->giftOptionsHelper = $giftOptionsHelper;
        $this->creditmemoHelper = $creditmemoHelper;
        $this->taxHelper = $taxHelper;
    }

    public function formData($item, $creditMemo, $transactionLineItems)
    {
        $amount = $this->lineItemsHelper->getAmount($item, $creditMemo->getOrderCurrencyCode());
        $amountTax = $this->lineItemsHelper->getStripeFormattedAmount($item->getTaxAmount(), $creditMemo->getOrderCurrencyCode());
        $this->amount = -$amount;
        $this->amountTax = -$amountTax;
        $this->originalLineItem = $this->getTransactionLineItemId($item, $creditMemo, $transactionLineItems);
        $this->reference = $this->lineItemsHelper->getReferenceForInvoiceTax($item, $creditMemo->getOrder());
        $this->quantity = $item->getQty();
    }

    public function formOfflineData($item, $creditMemo, $lineItemData)
    {
        // Get the amount from the credit memo item and subtract the amount reverted from it for both price and shipping
        $amount = $this->lineItemsHelper->getAmount($item, $creditMemo->getOrderCurrencyCode());
        $amount -= $item->getAmountReverted();
        // If the amount or tax of the line item is larger than the amount which is refunded in the credit memo,
        // we add the amount for the item from the credit memo for a partial revert
        // Otherwise we add the amount of the line item as the amount to be reverted and mark the item as fully
        // reverted
        if ($lineItemData['remaining_amount'] > $amount) {
            $this->amount = -$amount;
        } else {
            $this->amount = -$lineItemData['remaining_amount'];
        }
        $item->setAmountReverted($item->getAmountReverted() + abs($this->amount));

        $amountTax = $this->lineItemsHelper->getStripeFormattedAmount($item->getTaxAmount(), $creditMemo->getOrderCurrencyCode());
        $amountTax -= $item->getAmountTaxReverted();
        if ($lineItemData['remaining_amount_tax'] > $amountTax) {
            $this->amountTax = -$amountTax;
        } else {
            $this->amountTax = -$lineItemData['remaining_amount_tax'];
        }
        $item->setAmountTaxReverted($item->getAmountTaxReverted() + abs($this->amountTax));

        // Add the amounts to the total to be reverted for the credit memo
        $this->creditmemoHelper->updateAmountToRevert($creditMemo, $this->amount, $this->amountTax, $this->taxHelper->isProductAndPromotionTaxExclusive());

        $this->originalLineItem = $lineItemData['id'];
        $this->reference = $this->lineItemsHelper->getReferenceForInvoiceTax($item, $creditMemo->getOrder());
        $this->quantity = $item->getQty();
    }

    public function formItemGwData($item, $orderItem, $creditMemo, $transactionLineItems)
    {
        $amount = $this->giftOptionsHelper->getItemGiftOptionsAmount($orderItem, $creditMemo->getOrderCurrencyCode()) * $item->getQty();
        $amountTax = $this->lineItemsHelper->getStripeFormattedAmount($orderItem->getGwTaxAmount(), $creditMemo->getOrderCurrencyCode()) * $item->getQty();
        $this->amount = -$amount;
        $this->amountTax = -$amountTax;
        $this->quantity = $item->getQty();
        $this->reference = $this->giftOptionsHelper->getItemGwReferenceForInvoiceTax($item, $creditMemo->getOrder());
        $this->originalLineItem = $this->getTransactionGwLineItemId($item, $creditMemo, $transactionLineItems);
    }

    public function formOfflineItemGwData($item, $orderItem, $creditMemo, $transactionLineItems)
    {
        $this->formItemGwData($item, $orderItem, $creditMemo, $transactionLineItems);
        // Add the amounts to the total to be reverted for the credit memo
        $this->creditmemoHelper->updateAmountToRevert($creditMemo, $this->amount, $this->amountTax, $this->taxHelper->isProductAndPromotionTaxExclusive());
    }

    public function formOrderGwData($creditMemo, $transactionLineItems)
    {
        $amount = $this->giftOptionsHelper->getSalseObjectGiftOptionsAmount($creditMemo->getOrder(), $creditMemo->getOrderCurrencyCode());
        $amountTax = $this->lineItemsHelper->getStripeFormattedAmount($creditMemo->getOrder()->getGwTaxAmount(), $creditMemo->getOrderCurrencyCode());
        $this->amount = -$amount;
        $this->amountTax = -$amountTax;
        $this->quantity = 1;
        $this->reference = $this->giftOptionsHelper->getSalesObjectGiftOptionsReference($creditMemo->getOrder());
        $this->originalLineItem = $this->getTransactionOrderGwLineItemId($creditMemo, $transactionLineItems);
    }

    public function formOfflineOrderGwData($creditMemo, $transactionLineItems)
    {
        $this->formOrderGwData($creditMemo, $transactionLineItems);
        // Add the amounts to the total to be reverted for the credit memo
        $this->creditmemoHelper->updateAmountToRevert($creditMemo, $this->amount, $this->amountTax, $this->taxHelper->isProductAndPromotionTaxExclusive());
    }

    public function formOrderPrintedCardData($creditMemo, $transactionLineItems)
    {
        $amount = $this->giftOptionsHelper->getSalesObjectPrintedCardAmount($creditMemo->getOrder(), $creditMemo->getOrderCurrencyCode());
        $amountTax = $this->lineItemsHelper->getStripeFormattedAmount($creditMemo->getOrder()->getGwCardTaxAmount(), $creditMemo->getOrderCurrencyCode());
        $this->amount = -$amount;
        $this->amountTax = -$amountTax;
        $this->quantity = 1;
        $this->reference = $this->giftOptionsHelper->getSalesObjectPrintedCardReference($creditMemo->getOrder());
        $this->originalLineItem = $this->getTransactionOrderPrintedCardLineItemId($creditMemo, $transactionLineItems);
    }

    public function formOfflineOrderPrintedCardData($creditMemo, $transactionLineItems)
    {
        $this->formOrderPrintedCardData($creditMemo, $transactionLineItems);
        // Add the amounts to the total to be reverted for the credit memo
        $this->creditmemoHelper->updateAmountToRevert($creditMemo, $this->amount, $this->amountTax, $this->taxHelper->isProductAndPromotionTaxExclusive());
    }

    public function formItemAdditionalFeeData($item, $creditMemo, $additionalFee, $transactionLineItems)
    {
        $this->amount = $this->lineItemsHelper->getStripeFormattedAmount(-$additionalFee['amount'], $creditMemo->getOrderCurrencyCode());
        $this->amountTax = $this->lineItemsHelper->getStripeFormattedAmount(-$additionalFee['amount_tax'], $creditMemo->getOrderCurrencyCode());
        $this->originalLineItem = $this->getCreditmemoItemAdditionalFeeLineItemId($item, $creditMemo, $transactionLineItems, $additionalFee['code']);
        $this->reference = $this->lineItemsHelper->getReferenceForInvoiceAdditionalFee($item, $creditMemo->getOrder(), $additionalFee['code']);
        $this->quantity = $item->getQty();
    }

    public function formOfflineItemAdditionalFeeData($item, $creditMemo, $additionalFee, $transactionLineItems)
    {
        $this->formItemAdditionalFeeData($item, $creditMemo, $additionalFee, $transactionLineItems);
        // Add the amounts to the total to be reverted for the credit memo
        $this->creditmemoHelper->updateAmountToRevert($creditMemo, $this->amount, $this->amountTax, $this->taxHelper->isProductAndPromotionTaxExclusive());
    }

    public function formCreditmemoAdditionalFeeData($creditMemo, $additionalFee, $transactionLineItems)
    {
        $this->amount = $this->lineItemsHelper->getStripeFormattedAmount(-$additionalFee['amount'], $creditMemo->getOrderCurrencyCode());
        $this->amountTax = $this->lineItemsHelper->getStripeFormattedAmount(-$additionalFee['amount_tax'], $creditMemo->getOrderCurrencyCode());
        $this->originalLineItem = $this->getCreditmemoAdditionalFeeLineItemId($creditMemo, $transactionLineItems, $additionalFee['code']);
        $this->reference = $this->lineItemsHelper->getSalesEntityAdditionalFeeReference($creditMemo->getOrder(), $additionalFee['code']);
        $this->quantity = 1;
    }

    public function formOfflineCreditmemoAdditionalFeeData($creditMemo, $additionalFee, $transactionLineItems)
    {
        $this->formCreditmemoAdditionalFeeData($creditMemo, $additionalFee, $transactionLineItems);
        // Add the amounts to the total to be reverted for the credit memo
        $this->creditmemoHelper->updateAmountToRevert($creditMemo, $this->amount, $this->amountTax, $this->taxHelper->isProductAndPromotionTaxExclusive());
    }

    public function toArray()
    {
        return [
            self::AMOUNT_FIELD_NAME => $this->amount,
            self::AMOUNT_TAX_FIELD_NAME => $this->amountTax,
            self::ORIGINAL_LINE_ITEM_FIELD_NAME => $this->originalLineItem,
            self::REFERENCE_FIELD_NAME => $this->reference,
            self::QUANTITY_FIELD_NAME => $this->quantity,
        ];
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function getAmountTax()
    {
        return $this->amountTax;
    }

    public function getReference()
    {
        return $this->reference;
    }

    private function getTransactionLineItemId($item, $creditMemo, $transactionLineItems)
    {
        $reference = $this->lineItemsHelper->getReferenceForInvoiceTax($item, $creditMemo->getOrder());
        $lineItem = $this->lineItemsHelper->getLineItemByReference($reference, $transactionLineItems);

        return $lineItem ? $lineItem->id : null;
    }

    private function getCreditmemoItemAdditionalFeeLineItemId($item, $creditMemo, $transactionLineItems, $code)
    {
        $reference = $this->lineItemsHelper->getReferenceForInvoiceAdditionalFee($item, $creditMemo->getOrder(), $code);
        $lineItem = $this->lineItemsHelper->getLineItemByReference($reference, $transactionLineItems);

        return $lineItem ? $lineItem->id : null;
    }

    private function getTransactionGwLineItemId($item, $creditMemo, $transactionLineItems)
    {
        $reference = $this->giftOptionsHelper->getItemGwReferenceForInvoiceTax($item, $creditMemo->getOrder());
        $lineItem = $this->lineItemsHelper->getLineItemByReference($reference, $transactionLineItems);

        return $lineItem ? $this->getIdFromLineItem($lineItem) : null;
    }

    private function getTransactionOrderGwLineItemId($creditMemo, $transactionLineItems)
    {
        $reference = $this->giftOptionsHelper->getSalesObjectGiftOptionsReference($creditMemo->getOrder());
        $lineItem = $this->lineItemsHelper->getLineItemByReference($reference, $transactionLineItems);

        return $lineItem ? $this->getIdFromLineItem($lineItem) : null;
    }

    private function getTransactionOrderPrintedCardLineItemId($creditMemo, $transactionLineItems)
    {
        $reference = $this->giftOptionsHelper->getSalesObjectPrintedCardReference($creditMemo->getOrder());
        $lineItem = $this->lineItemsHelper->getLineItemByReference($reference, $transactionLineItems);

        return $lineItem ? $this->getIdFromLineItem($lineItem) : null;
    }

    private function getCreditmemoAdditionalFeeLineItemId($creditMemo, $transactionLineItems, $code)
    {
        $reference = $this->lineItemsHelper->getSalesEntityAdditionalFeeReference($creditMemo->getOrder(), $code);
        $lineItem = $this->lineItemsHelper->getLineItemByReference($reference, $transactionLineItems);

        return $lineItem ? $this->getIdFromLineItem($lineItem) : null;
    }

    private function getIdFromLineItem($lineItem)
    {
        if (is_array($lineItem)) {
            return $lineItem['id'];
        } else {
            return $lineItem->id;
        }
    }
}