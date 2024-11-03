<?php

namespace StripeIntegration\Tax\Test\Integration\Helper;

class Compare extends AbstractCompare
{
    public function __construct($test)
    {
        parent::__construct($test);
    }
    public function compareQuoteData($quote, $calculatedData, $entity = 'Quote')
    {
        $this->compareGeneralData($quote, $calculatedData, $entity);
    }

    public function compareOrderData($order, $calculatedData, $entity = 'Order')
    {
        $this->compareGeneralData($order, $calculatedData, $entity);
        $this->getTest()->assertNotNull($order->getStripeTaxCalculationId(), $entity . " 'stripe_tax_calculation_id' not set");
    }

    public function compareInvoiceData($invoice, $calculatedData, $entity = 'Invoice')
    {
        $this->compareGeneralData($invoice, $calculatedData, $entity);
        $this->getTest()->assertNotNull($invoice->getStripeTaxCalculationId(), $entity . " 'stripe_tax_calculation_id' not set");
        $this->getTest()->assertNotNull($invoice->getStripeTaxTransactionId(), $entity . " 'stripe_tax_transaction_id' not set");
    }

    public function compareQuoteItemData($quoteItem, $calculatedData, $entity = 'Quote')
    {
        $this->compareItemData($quoteItem, $calculatedData, $entity);
    }

    public function compareQuoteItemDataDifferentDefaultCurrency($quoteItem, $calculatedData, $entity = 'Quote')
    {
        // This is specific for the quote compare, as there is an issue with currencies which are different from base,
        // where the price of the quote item is set to the base price. The price will be checked in the case of
        // orders, invoices and credit memos
        unset($calculatedData['price']);
        $this->compareItemData($quoteItem, $calculatedData, $entity);
    }

    public function compareOrderItemData($quoteItem, $calculatedData, $entity = 'Order')
    {
        $this->compareItemData($quoteItem, $calculatedData, $entity);
    }

    public function compareInvoiceItemData($quoteItem, $calculatedData, $entity = 'Invoice')
    {
        $this->compareItemData($quoteItem, $calculatedData, $entity);
    }
}