<?php

namespace StripeIntegration\Tax\Test\Integration\Frontend\PriceCalculation;

use StripeIntegration\Tax\Test\Integration\Helper\Calculator;
use StripeIntegration\Tax\Test\Integration\Helper\Compare;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class BundleProductFixedPriceTest extends \PHPUnit\Framework\TestCase
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
     */
    public function testTaxExclusive()
    {
        $this->runTheTest('exclusive');
    }

    /**
     * @magentoConfigFixture current_store tax/stripe_tax/prices_and_promotions_tax_behavior inclusive
     * @magentoConfigFixture current_store tax/stripe_tax/shipping_tax_behavior inclusive
     */
    public function testTaxInclusive()
    {
        $this->runTheTest('inclusive');
    }

    private function runTheTest($taxBehaviour)
    {
        $this->quote->create()
            ->setCustomer('LoggedIn')
            ->setCart("BundleProductFixedPrice")
            ->setShippingAddress("Romania")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("Romania")
            ->setPaymentMethod("checkmo");

        $calculatedData = $this->calculator->calculateQuoteData(220, 2, 5, $taxBehaviour);
        $this->compare->compareQuoteData($this->quote->getQuote(), $calculatedData);

        $parentQuoteItem = $this->quote->getQuoteItem('bundle-fixed-price-simple-product-bundle-2-simple-product-bundle-4');
        $parentQuoteItemData = $this->calculator->calculateQuoteItemData(220, 10, 2, $taxBehaviour);
        $this->compare->compareQuoteItemData($parentQuoteItem, $parentQuoteItemData);

        // Compare Order data
        $order = $this->quote->placeOrder();
        $order = $this->orderHelper->refreshOrder($order);
        $this->compare->compareOrderData($order, $calculatedData);
        $orderItem = $this->orderHelper->getOrderItem($order, 'bundle-fixed-price-simple-product-bundle-2-simple-product-bundle-4');
        $this->compare->compareOrderItemData($orderItem, $parentQuoteItemData);

        // Create invoice and compare data
        \Magento\TestFramework\Helper\Bootstrap::getInstance()->loadArea('adminhtml');
        $this->tests->invoiceOnline($order, ['bundle-fixed-price-simple-product-bundle-2-simple-product-bundle-4' => 1]);
        $order = $this->orderHelper->refreshOrder($order);
        $invoicesCollection = $order->getInvoiceCollection();
        $this->assertEquals(1, $invoicesCollection->getSize());
        $invoice = $invoicesCollection->getFirstItem();
        $calculatedData = $this->calculator->calculateData(220, 1, 10, $taxBehaviour);
        $this->compare->compareInvoiceData($invoice, $calculatedData);
        $invoiceItemData = $this->calculator->calculateItemData(220, 10, 1, $taxBehaviour);
        $invoiceItem = $this->invoiceHelper->getInvoiceItem($invoice, 'bundle-fixed-price-simple-product-bundle-2-simple-product-bundle-4');
        $this->compare->compareInvoiceItemData($invoiceItem, $invoiceItemData);
        $shippingData = $this->calculator->calculateShippingData(220, 10, 1, $taxBehaviour);

        // Check expected total for first invoice which will include shipping
        $expectedInvoicedTotal = $invoiceItemData['price_incl_tax'] + $shippingData['shipping_incl_tax'];
        $this->assertEquals($expectedInvoicedTotal, $order->getTotalInvoiced());

        $this->tests->invoiceOnline($order, ['bundle-fixed-price-simple-product-bundle-2-simple-product-bundle-4' => 1]);
        $order = $this->orderHelper->refreshOrder($order);
        $invoicesCollection = $order->getInvoiceCollection();
        $this->assertEquals(2, $invoicesCollection->getSize());
        $invoice = $invoicesCollection->getLastItem();
        $calculatedData = $this->calculator->calculateData(220, 1, 0, $taxBehaviour);
        $this->compare->compareInvoiceData($invoice, $calculatedData);
        $invoiceItemData = $this->calculator->calculateItemData(220, 0, 1, $taxBehaviour);
        $invoiceItem = $this->invoiceHelper->getInvoiceItem($invoice, 'bundle-fixed-price-simple-product-bundle-2-simple-product-bundle-4');
        $this->compare->compareInvoiceItemData($invoiceItem, $invoiceItemData);

        $this->assertEquals($order->getGrandTotal(), $order->getTotalInvoiced());
    }
}
