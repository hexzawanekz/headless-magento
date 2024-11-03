<?php

namespace StripeIntegration\Tax\Plugin\Sales\Model\Order\Invoice\Total;

use Magento\Directory\Model\CurrencyFactory;
use StripeIntegration\Tax\Helper\Currency;
use StripeIntegration\Tax\Helper\GiftOptions;
use StripeIntegration\Tax\Helper\LineItems;
use StripeIntegration\Tax\Helper\TaxCalculator;
use StripeIntegration\Tax\Model\StripeTax;
use \Magento\Sales\Model\Order\Invoice\Total\Tax as InvoiceTax;
use \Magento\Sales\Model\Order\Invoice;
use StripeIntegration\Tax\Model\Order\Transaction\Item;

class Tax
{
    private $stripeTax;
    private $transactionItem;
    private $taxCalculationHelper;
    private $currencyFactory;
    private $lineItemsHelper;
    private $invoiceHelper;
    private $giftOptionsHelper;
    private $currencyHelper;

    public function __construct(
        StripeTax $stripeTax,
        Item $transactionItem,
        TaxCalculator $taxCalculationHelper,
        CurrencyFactory $currencyFactory,
        LineItems $lineItemsHelper,
        \StripeIntegration\Tax\Helper\Invoice $invoiceHelper,
        GiftOptions $giftOptionsHelper,
        Currency $currencyHelper
    )
    {
        $this->stripeTax = $stripeTax;
        $this->transactionItem = $transactionItem;
        $this->taxCalculationHelper = $taxCalculationHelper;
        $this->currencyFactory = $currencyFactory;
        $this->lineItemsHelper = $lineItemsHelper;
        $this->invoiceHelper = $invoiceHelper;
        $this->giftOptionsHelper = $giftOptionsHelper;
        $this->currencyHelper = $currencyHelper;
    }

    /**
     * Create the Stripe tax calculation request and perform the call, and after that we take the results from the
     * calculation and add them to the invoice.
     * Most of the fields will coincide with the ones being set in the original method, but we will also add
     * recalculated fields like the ones containing tax, to avoid situations where there might have been some
     * legislative changes between when the order and invoice are created.
     *
     * @param InvoiceTax $subject
     * @param callable $proceed
     * @param Invoice $invoice
     * @return $this
     */
    public function aroundCollect(InvoiceTax $subject, callable $proceed, Invoice $invoice)
    {
        if ($this->stripeTax->isEnabled()) {
            $this->stripeTax->calculateForInvoiceTax($invoice->getOrder(), $invoice);
            if ($this->stripeTax->hasValidResponse()) {
                return $this->setStripeCalculatedValues($invoice);
            }
        }

        return $proceed($invoice);
    }

    private function setStripeCalculatedValues($invoice)
    {
        $totalTax = 0;
        $baseTotalTax = 0;
        $totalDiscountTaxCompensation = 0;
        $baseTotalDiscountTaxCompensation = 0;
        $subtotal = 0;
        $baseSubtotal = 0;
        $subtotalInclTax = 0;
        $baseSubtotalInclTax = 0;

        $response = $this->stripeTax->getResponse();
        $order = $invoice->getOrder();
        foreach ($invoice->getAllItems() as $item) {
            $orderItem = $item->getOrderItem();
            $orderItemQty = $orderItem->getQtyOrdered();

            if ($orderItemQty) {
                if ($orderItem->isDummy() || $item->getQty() <= 0) {
                    // in case the order item is a bundle dynamic product, add the GW data to the invoice item
                    if ($orderItem->getHasChildren() &&
                        $orderItem->isChildrenCalculated() &&
                        $this->giftOptionsHelper->itemHasGiftOptions($orderItem)
                    ) {
                        $this->setItemGwCalculatedData($response, $item, $invoice);
                    }
                    $this->handleInvoiceItemAdditionalFees($item, $order, $response, $invoice);

                    continue;
                }
                // Calculate the prices based on the results from the calculation API for the item and add them to the
                // corresponding totals
                $lineItem = $response->getLineItemData($this->lineItemsHelper->getReferenceForInvoiceTax($item, $invoice->getOrder()));
                $this->transactionItem->prepare($item, $lineItem);
                $calculatedValues = $this->getCalculatedValues($invoice->getBaseToOrderRate(), $invoice->getBaseCurrencyCode());
                $this->transactionItem->setUseBaseCurrency(true)
                    ->setBaseCurrencyPrices($item);
                $baseCalculatedValues = $this->getCalculatedValues($invoice->getBaseToOrderRate(), $invoice->getBaseCurrencyCode());

                $item->setTaxAmount($calculatedValues['row_tax']);
                $item->setBaseTaxAmount($baseCalculatedValues['row_tax']);
                $item->setDiscountTaxCompensationAmount($calculatedValues['discount_tax_compensation']);
                $item->setBaseDiscountTaxCompensationAmount($baseCalculatedValues['discount_tax_compensation']);

                $this->setItemAdditionalData($item, $calculatedValues, $baseCalculatedValues);

                $subtotal += $calculatedValues['row_total'];
                $baseSubtotal += $baseCalculatedValues['row_total'];
                $subtotalInclTax += $calculatedValues['row_total_incl_tax'];
                $baseSubtotalInclTax += $baseCalculatedValues['row_total_incl_tax'];

                $totalTax += $calculatedValues['row_tax'];
                $baseTotalTax += $baseCalculatedValues['row_tax'];
                $totalDiscountTaxCompensation += $calculatedValues['discount_tax_compensation'];
                $baseTotalDiscountTaxCompensation += $baseCalculatedValues['discount_tax_compensation'];

                if ($this->giftOptionsHelper->itemHasGiftOptions($orderItem)) {
                    $this->setItemGwCalculatedData($response, $item, $invoice);
                }

                $this->handleInvoiceItemAdditionalFees($item, $order, $response, $invoice);
            }
        }

        $this->setInvoiceAdditionalData($invoice, $subtotal, $baseSubtotal, $subtotalInclTax, $baseSubtotalInclTax);

        $taxDiscountCompensationAmt = $totalDiscountTaxCompensation;
        $baseTaxDiscountCompensationAmt = $baseTotalDiscountTaxCompensation;

        if ($this->invoiceHelper->canIncludeShipping($invoice)) {
            // Calculate the prices based on the results from the calculation API for shipping and add them to the
            // corresponding totals
            $shippingItem = $response->getShippingCostData();
            $this->transactionItem->prepareForShipping($invoice, $shippingItem);
            $calculatedShippingValues = $this->getCalculatedValues($invoice->getBaseToOrderRate(), $invoice->getBaseCurrencyCode());
            $this->transactionItem->setUseBaseCurrency(true)
                ->setBaseCurrencyPricesForShipping($invoice);
            $baseCalculatedShippingValues = $this->getCalculatedValues($invoice->getBaseToOrderRate(), $invoice->getBaseCurrencyCode());

            $totalTax += $calculatedShippingValues['row_tax'];
            $baseTotalTax += $baseCalculatedShippingValues['row_tax'];
            $totalDiscountTaxCompensation += $calculatedShippingValues['discount_tax_compensation'];
            $baseTotalDiscountTaxCompensation += $baseCalculatedShippingValues['discount_tax_compensation'];

            $this->setAdditionalInvoiceShippingData($invoice, $calculatedShippingValues, $baseCalculatedShippingValues);

            $invoice->setShippingTaxAmount($calculatedShippingValues['row_tax']);
            $invoice->setBaseShippingTaxAmount($baseCalculatedShippingValues['row_tax']);
            $invoice->setShippingDiscountTaxCompensationAmount($calculatedShippingValues['discount_tax_compensation']);
            $invoice->setBaseShippingDiscountTaxCompensationAmnt($baseCalculatedShippingValues['discount_tax_compensation']);
        }

        if ($this->giftOptionsHelper->salesObjectHasGiftOptions($order) &&
            $order->getGwTaxAmount() != $order->getGwTaxAmountInvoiced()
        ) {
            $this->setGwCalculatedData($response, $order, $invoice);
        }

        if ($this->giftOptionsHelper->salesObjectHasPrintedCard($order) &&
            $order->getGwCardTaxAmount() != $order->getGwCardTaxInvoiced()
        ) {
            $this->setPrintedCardCalculatedData($response, $order, $invoice);
        }

        // Set the totals for the invoice
        $invoice->setTaxAmount($totalTax);
        $invoice->setBaseTaxAmount($baseTotalTax);
        $invoice->setDiscountTaxCompensationAmount($taxDiscountCompensationAmt);
        $invoice->setBaseDiscountTaxCompensationAmount($baseTaxDiscountCompensationAmt);

        $invoice->setGrandTotal($invoice->getGrandTotal() + $totalTax + $totalDiscountTaxCompensation);
        $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $baseTotalTax + $baseTotalDiscountTaxCompensation);

        $this->handleInvoiceAdditionalFees($invoice, $order, $response);

        $invoice->setStripeTaxCalculationId($response->getTaxCalculationId());

        return $this;
    }

    /**
     * Sets the values which may change in case the tax rate changes between the point of order creation and the
     * point of invoice creation for invoice items
     */
    private function setItemAdditionalData($item, $calculatedValues, $baseCalculatedValues)
    {
        $item->setPriceInclTax($calculatedValues['price_incl_tax']);
        $item->setBasePriceInclTax($baseCalculatedValues['price_incl_tax']);
        $item->setRowTotalInclTax($calculatedValues['row_total_incl_tax']);
        $item->setBaseRowTotalInclTax($baseCalculatedValues['row_total_incl_tax']);
    }

    /**
     * Sets invoice shipping related values which may change in case the tax rate changes between the point of order creation
     * and the point of invoice creation
     */
    private function setAdditionalInvoiceShippingData($invoice, $calculatedShippingValues, $baseCalculatedShippingValues)
    {
        $invoice->setShippingInclTax($calculatedShippingValues['row_total_incl_tax']);
        $invoice->setBaseShippingInclTax($baseCalculatedShippingValues['row_total_incl_tax']);
    }

    /**
     * Sets invoice related values which may change in case the tax rate changes between the point of order creation
     * and the point of invoice creation
     */
    private function setInvoiceAdditionalData($invoice, $subtotal, $baseSubtotal, $subtotalInclTax, $baseSubtotalInclTax)
    {
        $invoice->setSubtotalInclTax($subtotalInclTax);
        $invoice->setBaseSubtotalInclTax($baseSubtotalInclTax);
    }

    /**
     * Handler for the additional fees added by a 3rd party developer at invoice level.
     * Looks in the additional_fees property of the invoice which will be set during the request formation and based on
     * it, we will get the calculated tax value for the additional fee. This calculated fee will be calculated in the
     * base currency as well, and then added to the grand total and the tax amount of the invoice.
     * The tax and base tax value will be added to the invoice again to be provided further to the 3rd party
     * developer if they will need it to complete other custom fields on their side.
     *
     * @param $invoice
     * @param $order
     * @param $response
     * @return void
     */
    private function handleInvoiceAdditionalFees($invoice, $order, $response)
    {
        if ($invoice->getAdditionalFees()) {
            $taxes = [];
            foreach ($invoice->getAdditionalFees() as $invoiceAdditionalFee) {
                $lineItem = $response->getLineItemData($this->lineItemsHelper->getSalesEntityAdditionalFeeReference($order, $invoiceAdditionalFee));
                $taxes[$invoiceAdditionalFee]['tax'] = $this->currencyHelper->stripeAmountToMagentoAmount(
                    $lineItem['stripe_total_calculated_tax'],
                    $order->getOrderCurrencyCode()
                );
                $taxes[$invoiceAdditionalFee]['base_tax'] = $this->currencyHelper->stripeAmountToMagentoAmount(
                    $this->getStripeAmountBaseValue($lineItem['stripe_total_calculated_tax'], $order),
                    $order->getBaseCurrencyCode()
                );

                $invoice->setTaxAmount($invoice->getTaxAmount() + $taxes[$invoiceAdditionalFee]['tax']);
                $invoice->setBaseTaxAmount($invoice->getBaseTaxAmount() + $taxes[$invoiceAdditionalFee]['base_tax']);
                $invoice->setGrandTotal($invoice->getGrandTotal() + $taxes[$invoiceAdditionalFee]['tax']);
                $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $taxes[$invoiceAdditionalFee]['base_tax']);
            }

            $invoice->setAdditionalFeesTax($taxes);
        }
    }

    /**
     * Handler for the additional fees added by a 3rd party developer at invoice item level.
     * Looks in the additional_fees property of the invoice item which will be set during the request formation and
     * based on it, we will get the calculated tax value for the additional fee. This calculated fee will be calculated
     * in the base currency as well, and then added to the grand total, the tax amount of the invoice,
     * and the tax amount of the item.
     * The tax and base tax value will be added to the invoice item again to be provided further to the 3rd party
     * developer if they will need it to complete other custom fields on their side.
     *
     * @param $item
     * @param $order
     * @param $response
     * @param $invoice
     * @return void
     */
    private function handleInvoiceItemAdditionalFees($item, $order, $response, $invoice)
    {
        if ($item->getAdditionalFees()) {
            $taxes = [];
            foreach ($item->getAdditionalFees() as $invoiceAdditionalFee) {
                $lineItem = $response->getLineItemData($this->lineItemsHelper->getReferenceForInvoiceAdditionalFee($item, $order, $invoiceAdditionalFee));
                $taxes[$invoiceAdditionalFee]['tax'] = $this->currencyHelper->stripeAmountToMagentoAmount(
                    $lineItem['stripe_total_calculated_tax'],
                    $order->getOrderCurrencyCode()
                );
                $taxes[$invoiceAdditionalFee]['base_tax'] = $this->currencyHelper->stripeAmountToMagentoAmount(
                    $this->getStripeAmountBaseValue($lineItem['stripe_total_calculated_tax'], $order),
                    $order->getBaseCurrencyCode()
                );

                $item->setTaxAmount($item->getTaxAmount() + $taxes[$invoiceAdditionalFee]['tax']);
                $item->setBaseTaxAmount($item->getBaseTaxAmount() + $taxes[$invoiceAdditionalFee]['base_tax']);
                $invoice->setTaxAmount($invoice->getTaxAmount() + $taxes[$invoiceAdditionalFee]['tax']);
                $invoice->setBaseTaxAmount($invoice->getBaseTaxAmount() + $taxes[$invoiceAdditionalFee]['base_tax']);
                $invoice->setGrandTotal($invoice->getGrandTotal() + $taxes[$invoiceAdditionalFee]['tax']);
                $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $taxes[$invoiceAdditionalFee]['base_tax']);
            }

            $item->setAdditionalFeesTax($taxes);
        }

    }

    private function getStripeAmountBaseValue($value, $order)
    {
        $orderToBaseRate = round(1 / $order->getBaseToOrderRate(), 4);
        return round($value * $orderToBaseRate);
    }

    /**
     * Sets calculated values for the Gift Wrapping options for items which will be sent forward to be set in the
     * tax calculation collector of the module
     *
     * @param $response
     * @param $item
     * @param $invoice
     * @return void
     */
    private function setItemGwCalculatedData($response, $item, $invoice)
    {
        $lineItem = $response->getLineItemData($this->giftOptionsHelper->getItemGwReferenceForInvoiceTax($item, $invoice->getOrder()));
        $this->transactionItem->prepareItemGw($item, $lineItem);
        $calculatedValues = $this->getCalculatedValues($invoice->getBaseToOrderRate(), $invoice->getBaseCurrencyCode());
        $this->transactionItem->setUseBaseCurrency(true)
            ->setItemGwBaseCurrencyPrices($item);
        $baseCalculatedValues = $this->getCalculatedValues($invoice->getBaseToOrderRate(), $invoice->getBaseCurrencyCode());

        $item->setStripeItemGwCalculatedValues($calculatedValues);
        $item->setStripeItemGwBaseCalculatedValues($baseCalculatedValues);
    }

    /**
     * Sets calculated values for the Gift Wrapping options invoice which will be sent forward to be set in the
     * tax calculation collector of the module
     *
     * @param $response
     * @param $order
     * @param $invoice
     * @return void
     */
    private function setGwCalculatedData($response, $order, $invoice)
    {
        $lineItem = $response->getLineItemData($this->giftOptionsHelper->getSalesObjectGiftOptionsReference($order));
        $this->transactionItem->prepareSalesObjectGW($order, $lineItem);
        $calculatedValues = $this->getCalculatedValues($invoice->getBaseToOrderRate(), $invoice->getBaseCurrencyCode());
        $this->transactionItem->setUseBaseCurrency(true)
            ->setSalesObjectGwBaseCurrencyPrices($order);
        $baseCalculatedValues = $this->getCalculatedValues($invoice->getBaseToOrderRate(), $invoice->getBaseCurrencyCode());

        $invoice->setStripeGwCalculatedValues($calculatedValues);
        $invoice->setStripeGwBaseCalculatedValues($baseCalculatedValues);
    }

    /**
     * Sets calculated values for the Gift Wrapping printed card options invoice which will be sent forward to be
     * set in the tax calculation collector of the module
     *
     * @param $response
     * @param $order
     * @param $invoice
     * @return void
     */
    private function setPrintedCardCalculatedData($response, $order, $invoice)
    {
        $lineItem = $response->getLineItemData($this->giftOptionsHelper->getSalesObjectPrintedCardReference($order));
        $this->transactionItem->preparePrintedCard($order, $lineItem);
        $calculatedValues = $this->getCalculatedValues($invoice->getBaseToOrderRate(), $invoice->getBaseCurrencyCode());
        $this->transactionItem->setUseBaseCurrency(true)
            ->setPrintedCardBaseCurrencyPrices($order);
        $baseCalculatedValues = $this->getCalculatedValues($invoice->getBaseToOrderRate(), $invoice->getBaseCurrencyCode());

        $invoice->setStripePrintedCardCalculatedValues($calculatedValues);
        $invoice->setStripePrintedCardBaseCalculatedValues($baseCalculatedValues);
    }

    private function getCalculatedValues($baseToOrderRate, $baseCurrencyCode)
    {
        $stripeValues = $this->taxCalculationHelper->getStripeCalculatedValuesForInvoice(
            $this->transactionItem, $baseToOrderRate, $baseCurrencyCode
        );

        return $this->taxCalculationHelper->calculatePrices(
            $this->transactionItem, $stripeValues, $this->transactionItem->getQuantity()
        );
    }
}