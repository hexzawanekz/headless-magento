<?php

namespace StripeIntegration\Tax\Test\Integration\Frontend\PriceCalculation;

use StripeIntegration\Tax\Test\Integration\Helper\Calculator;
use StripeIntegration\Tax\Test\Integration\Helper\Compare;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class VirtualProductTest extends \PHPUnit\Framework\TestCase
{
    private $objectManager;
    private $quote;
    private $compare;
    private $calculator;
    private $productRepository;
    private $tests;
    private $orderHelper;
    private $invoiceHelper;

    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->quote = new \StripeIntegration\Tax\Test\Integration\Helper\Quote();
        $this->compare = new Compare($this);
        $this->calculator = new Calculator('Romania');
        $this->productRepository = $this->objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
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

    // Come back to this once MAGENTO-791 is fixed
//    /**
//     * @magentoConfigFixture current_store tax/stripe_tax/prices_and_promotions_tax_behavior inclusive
//     * @magentoConfigFixture current_store tax/stripe_tax/shipping_tax_behavior inclusive
//     */
//    public function testTaxInclusive()
//    {
//        $this->runTheTest('inclusive');
//    }

    private function runTheTest($taxBehaviour)
    {
        $this->quote->create()
            ->setCustomer('LoggedIn')
            ->setCart("Virtual")
            ->setBillingAddress("Romania")
            ->setPaymentMethod("checkmo");

        $quoteItem = $this->quote->getQuoteItem('virtual-product');
        $product = $this->productRepository->get($quoteItem->getSku());

        $price = $product->getPrice();
        $calculatedData = $this->calculator->calculateQuoteData($price, 2, 0, $taxBehaviour);
        $this->compare->compareQuoteData($this->quote->getQuote(), $calculatedData);

        $quoteItemData = $this->calculator->calculateQuoteItemData($price, 0, 2, $taxBehaviour);
        $this->compare->compareQuoteItemData($quoteItem, $quoteItemData);

        // Compare Order data
        $order = $this->quote->placeOrder();
        $order = $this->orderHelper->refreshOrder($order);
        $this->compare->compareOrderData($order, $calculatedData);
        $orderItem = $this->orderHelper->getOrderItem($order, 'virtual-product');
        $this->compare->compareOrderItemData($orderItem, $quoteItemData);

        $orderGrandTotal = $order->getGrandTotal();

        // Create invoice and compare data
        \Magento\TestFramework\Helper\Bootstrap::getInstance()->loadArea('adminhtml');
        $this->tests->invoiceOnline($order, ['virtual-product' => 1]);
        $order = $this->orderHelper->refreshOrder($order);
        $invoicesCollection = $order->getInvoiceCollection();
        $this->assertEquals(1, $invoicesCollection->getSize());
        $invoice = $invoicesCollection->getFirstItem();
        $calculatedData = $this->calculator->calculateData($price, 1, 0, $taxBehaviour);
        $this->compare->compareInvoiceData($invoice, $calculatedData);
        $invoiceItemData = $this->calculator->calculateItemData($price, 0, 1, $taxBehaviour);
        $invoiceItem = $this->invoiceHelper->getInvoiceItem($invoice, 'virtual-product');
        $this->compare->compareInvoiceItemData($invoiceItem, $invoiceItemData);

        // Check for intermediate value if the invoiced total for order
        $this->assertEquals($invoiceItem['price_incl_tax'], $order->getTotalInvoiced());

        // Pause for 1 second to make sure the request for invoice is different from the previous one
        // Because this order contains only a virtual product with qty 2, the requests for invoice will be the same
        // Usually if there are physical products, the first invoice contains the shipping amount as well,
        // and that will make it different from the next one which will not have it
        sleep(1);
        \Magento\TestFramework\Helper\Bootstrap::getInstance()->loadArea('adminhtml');
        $this->tests->invoiceOnline($order, ['virtual-product' => 1]);
        $order = $this->orderHelper->refreshOrder($order);
        $invoicesCollection = $order->getInvoiceCollection();
        $this->assertEquals(2, $invoicesCollection->getSize());
        $invoice = $invoicesCollection->getLastItem();
        $calculatedData = $this->calculator->calculateData($price, 1, 0, $taxBehaviour);
        $this->compare->compareInvoiceData($invoice, $calculatedData);
        $invoiceItemData = $this->calculator->calculateItemData($price, 0, 1, $taxBehaviour);
        $invoiceItem = $this->invoiceHelper->getInvoiceItem($invoice, 'virtual-product');
        $this->compare->compareInvoiceItemData($invoiceItem, $invoiceItemData);

        // Check that the whole amount of the order was invoiced
        $this->assertEquals($orderGrandTotal, $order->getTotalInvoiced());
    }
}
