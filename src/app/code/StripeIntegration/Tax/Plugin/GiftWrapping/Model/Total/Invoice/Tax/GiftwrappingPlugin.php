<?php

namespace StripeIntegration\Tax\Plugin\GiftWrapping\Model\Total\Invoice\Tax;

use Magento\Sales\Model\Order\Invoice;
use StripeIntegration\Tax\Model\StripeTax;
use Magento\GiftWrapping\Model\Total\Invoice\Tax\Giftwrapping;

class GiftwrappingPlugin
{
    private $stripeTax;

    public function __construct(
        StripeTax $stripeTax
    )
    {
        $this->stripeTax = $stripeTax;
    }

    public function aroundCollect(
        Giftwrapping $subject,
        callable $proceed,
        Invoice $invoice
    )
    {
        if ($this->stripeTax->isEnabled() && $this->stripeTax->hasValidResponse()) {
            $order = $invoice->getOrder();

            /**
             * Wrapping for items
             */
            $invoiced = 0;
            $baseInvoiced = 0;
            foreach ($invoice->getAllItems() as $invoiceItem) {
                if (!$invoiceItem->getQty() || $invoiceItem->getQty() == 0) {
                    continue;
                }
                $orderItem = $invoiceItem->getOrderItem();
                if ($orderItem->getGwId() &&
                    $orderItem->getGwBaseTaxAmount() &&
                    $orderItem->getQtyOrdered() >= ($orderItem->getQtyInvoiced() + $invoiceItem->getQty())
                ) {
                    $stripeCalculatedValues = $invoiceItem->getStripeItemGwCalculatedValues();
                    $stripeBaseCalculatedValues = $invoiceItem->getStripeItemGwBaseCalculatedValues();
                    $orderItem->setGwBaseTaxAmountInvoiced($stripeBaseCalculatedValues['tax']);
                    $orderItem->setGwTaxAmountInvoiced($stripeCalculatedValues['tax']);
                    $baseInvoiced += $orderItem->getGwBaseTaxAmount() * $invoiceItem->getQty();
                    $invoiced += $orderItem->getGwTaxAmount() * $invoiceItem->getQty();
                }
            }
            if ($invoiced > 0 || $baseInvoiced > 0) {
                $order->setGwItemsBaseTaxInvoiced($order->getGwItemsBaseTaxInvoiced() + $baseInvoiced);
                $order->setGwItemsTaxInvoiced($order->getGwItemsTaxInvoiced() + $invoiced);
                $invoice->setGwItemsBaseTaxAmount($baseInvoiced);
                $invoice->setGwItemsTaxAmount($invoiced);
            }

            /**
             * Wrapping for order
             */
            if ($order->getGwId() &&
                $order->getGwBaseTaxAmount() &&
                !$order->getGwBaseTaxAmountInvoiced()
            ) {
                $stripeCalculatedValues = $invoice->getStripeGwCalculatedValues();
                $stripeBaseCalculatedValues = $invoice->getStripeGwBaseCalculatedValues();
                $order->setGwBaseTaxAmountInvoiced($stripeBaseCalculatedValues['tax']);
                $order->setGwTaxAmountInvoiced($stripeCalculatedValues['tax']);
                $invoice->setGwBaseTaxAmount($stripeBaseCalculatedValues['tax']);
                $invoice->setGwTaxAmount($stripeCalculatedValues['tax']);
            }

            /**
             * Printed card
             */
            if ($order->getGwAddCard() &&
                $order->getGwCardBaseTaxAmount() &&
                !$order->getGwCardBaseTaxInvoiced()
            ) {
                $stripeCalculatedValues = $invoice->getStripePrintedCardCalculatedValues();
                $stripeBaseCalculatedValues = $invoice->getStripePrintedCardBaseCalculatedValues();
                $order->setGwCardBaseTaxInvoiced($stripeBaseCalculatedValues['tax']);
                $order->setGwCardTaxInvoiced($stripeCalculatedValues['tax']);
                $invoice->setGwCardBaseTaxAmount($stripeBaseCalculatedValues['tax']);
                $invoice->setGwCardTaxAmount($stripeCalculatedValues['tax']);
            }

            $baseTaxAmount = $invoice->getGwItemsBaseTaxAmount() + $invoice->getGwBaseTaxAmount() + $invoice->getGwCardBaseTaxAmount();
            $taxAmount = $invoice->getGwItemsTaxAmount() + $invoice->getGwTaxAmount() + $invoice->getGwCardTaxAmount();
            if ((float)$taxAmount > 0) {
                $invoice->setTaxAmount($invoice->getTaxAmount() + $taxAmount);
                $invoice->setGrandTotal($invoice->getGrandTotal() + $taxAmount);
            }
            if ((float)$baseTaxAmount > 0) {
                $invoice->setBaseTaxAmount($invoice->getBaseTaxAmount() + $baseTaxAmount);
                $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $baseTaxAmount);
            }

            return $this;
        }

        return $proceed($invoice);
    }
}