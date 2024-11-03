<?php

namespace StripeIntegration\Tax\Test\Integration\Frontend\PriceCalculation;

use StripeIntegration\Tax\Test\Integration\Helper\Calculator;
use StripeIntegration\Tax\Test\Integration\Helper\Compare;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class BundleProductDynamicTest extends \PHPUnit\Framework\TestCase
{
    private $objectManager;
    private $quote;
    private $compare;
    private $calculator;
    private $tests;
    private $orderHelper;
    private $invoiceHelper;

    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->quote = new \StripeIntegration\Tax\Test\Integration\Helper\Quote();
        $this->compare = new Compare($this);
        $this->calculator = new Calculator('Romania');
        $this->tests = new \StripeIntegration\Tax\Test\Integration\Helper\Tests();
        $this->orderHelper = new \StripeIntegration\Tax\Test\Integration\Helper\Order();
        $this->invoiceHelper = new \StripeIntegration\Tax\Test\Integration\Helper\Invoice();
    }

    /**
     * @magentoConfigFixture current_store tax/stripe_tax/prices_and_promotions_tax_behavior exclusive
     * @magentoConfigFixture current_store tax/stripe_tax/shipping_tax_behavior exclusive
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Tax/Test/Integration/_files/Data/ApiKeys.php
     */
    public function testTaxExclusive()
    {
        $this->runTheTest('exclusive');
    }

    /**
     * @magentoConfigFixture current_store tax/stripe_tax/prices_and_promotions_tax_behavior inclusive
     * @magentoConfigFixture current_store tax/stripe_tax/shipping_tax_behavior inclusive
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Tax/Test/Integration/_files/Data/ApiKeys.php
     */
    public function testTaxInclusive()
    {
        $this->runTheTest('inclusive');
    }

    private function runTheTest($taxBehaviour)
    {
        $this->quote->create()
            ->setCustomer('LoggedIn')
            ->setCart("BundleProductDynamic")
            ->setShippingAddress("Romania")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("Romania")
            ->setPaymentMethod("checkmo");

        $calculatedData = $this->calculator->calculateQuoteData(160, 2, 5, $taxBehaviour);
        $this->compare->compareQuoteData($this->quote->getQuote(), $calculatedData);

        $parentQuoteItem = $this->quote->getQuoteItem('bundle-dynamic-simple-product-bundle-1-simple-product-bundle-3');
        $parentQuoteItemData = $this->calculator->calculateQuoteItemData(160, 10, 2, $taxBehaviour);
        $this->compare->compareQuoteItemData($parentQuoteItem, $parentQuoteItemData);

        $childItem1 = $this->quote->getQuoteItem('simple-product-bundle-1');
        $quoteItemData = $this->calculator->calculateQuoteItemData(30, 0, 4, $taxBehaviour);
        $this->compare->compareQuoteItemData($childItem1, $quoteItemData);

        $childItem2 = $this->quote->getQuoteItem('simple-product-bundle-3');
        $quoteItemData = $this->calculator->calculateQuoteItemData(50, 0, 4, $taxBehaviour);
        $this->compare->compareQuoteItemData($childItem2, $quoteItemData);

        // Compare Order data
        $order = $this->quote->placeOrder();
        $order = $this->orderHelper->refreshOrder($order);
        $this->compare->compareOrderData($order, $calculatedData);

        $orderItem = $this->orderHelper->getOrderItem($order, 'bundle-dynamic-simple-product-bundle-1-simple-product-bundle-3');
        $this->compare->compareOrderItemData($orderItem, $parentQuoteItemData);

        $childItem1 = $this->orderHelper->getOrderItem($order, 'simple-product-bundle-1');
        $invoiceItemData = $this->calculator->calculateItemData(30, 0, 4, $taxBehaviour);
        $this->compare->compareQuoteItemData($childItem1, $invoiceItemData);

        $childItem2 = $this->orderHelper->getOrderItem($order, 'simple-product-bundle-3');
        $invoiceItemData = $this->calculator->calculateQuoteItemData(50, 0, 4, $taxBehaviour);
        $this->compare->compareQuoteItemData($childItem2, $invoiceItemData);

        // Create invoice and compare data
        \Magento\TestFramework\Helper\Bootstrap::getInstance()->loadArea('adminhtml');
        $this->tests->invoiceOnline($order, ['bundle-dynamic-simple-product-bundle-1-simple-product-bundle-3' => 1]);
        $order = $this->orderHelper->refreshOrder($order);
        $invoicesCollection = $order->getInvoiceCollection();
        $this->assertEquals(1, $invoicesCollection->getSize());
        $invoice = $invoicesCollection->getFirstItem();
        $calculatedData = $this->calculator->calculateData(160, 1, 10, $taxBehaviour);
        $this->compare->compareInvoiceData($invoice, $calculatedData);
        $invoiceItemData = $this->calculator->calculateItemData(30, 10, 2, $taxBehaviour);
        $invoiceItem = $this->invoiceHelper->getInvoiceItem($invoice, 'simple-product-bundle-1');
        $this->compare->compareInvoiceItemData($invoiceItem, $invoiceItemData);
        $invoiceItemData2 = $this->calculator->calculateItemData(50, 10, 2, $taxBehaviour);
        $invoiceItem2 = $this->invoiceHelper->getInvoiceItem($invoice, 'simple-product-bundle-3');
        $this->compare->compareInvoiceItemData($invoiceItem2, $invoiceItemData2);
        $shippingData = $this->calculator->calculateShippingData(160, 10, 1, $taxBehaviour);

        // Check expected total for first invoice which will include shipping
        $expectedInvoicedTotal = $invoiceItemData['row_total_incl_tax'] + $invoiceItemData2['row_total_incl_tax'] + $shippingData['shipping_incl_tax'];
        $this->assertEquals($expectedInvoicedTotal, $order->getTotalInvoiced());

        $this->tests->invoiceOnline($order, ['bundle-dynamic-simple-product-bundle-1-simple-product-bundle-3' => 1]);
        $order = $this->orderHelper->refreshOrder($order);
        $invoicesCollection = $order->getInvoiceCollection();
        $this->assertEquals(2, $invoicesCollection->getSize());
        $invoice = $invoicesCollection->getLastItem();
        $calculatedData = $this->calculator->calculateData(160, 1, 0, $taxBehaviour);
        $this->compare->compareInvoiceData($invoice, $calculatedData);
        $invoiceItemData = $this->calculator->calculateItemData(30, 0, 2, $taxBehaviour);
        $invoiceItem = $this->invoiceHelper->getInvoiceItem($invoice, 'simple-product-bundle-1');
        $this->compare->compareInvoiceItemData($invoiceItem, $invoiceItemData);
        $invoiceItemData2 = $this->calculator->calculateItemData(50, 0, 2, $taxBehaviour);
        $invoiceItem2 = $this->invoiceHelper->getInvoiceItem($invoice, 'simple-product-bundle-3');
        $this->compare->compareInvoiceItemData($invoiceItem2, $invoiceItemData2);

        $this->assertEquals($order->getGrandTotal(), $order->getTotalInvoiced());
    }
}
