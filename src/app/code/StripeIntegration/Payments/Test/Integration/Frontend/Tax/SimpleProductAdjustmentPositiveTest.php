<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\Tax;

use StripeIntegration\Tax\Test\Integration\Helper\Calculator;
use StripeIntegration\Tax\Test\Integration\Helper\Compare;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class SimpleProductAdjustmentPositiveTest extends \PHPUnit\Framework\TestCase
{
    private $objectManager;
    private $quote;
    private $compare;
    private $calculator;
    private $productRepository;
    private $tests;
    private $orderHelper;
    private $invoiceHelper;
    private $creditmemoHelper;
    private $stripeConfig;

    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();
        $this->compare = new Compare($this);
        $this->calculator = new Calculator('Romania');
        $this->productRepository = $this->objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests($this);
        $this->orderHelper = new \StripeIntegration\Tax\Test\Integration\Helper\Order();
        $this->invoiceHelper = new \StripeIntegration\Tax\Test\Integration\Helper\Invoice();
        $this->creditmemoHelper = new \StripeIntegration\Payments\Test\Integration\Helper\Creditmemo();
        $this->stripeConfig = $this->objectManager->get(\StripeIntegration\Tax\Model\Config::class);
    }

    /**
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Tax/Test/Integration/_files/Data/Enable.php
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Tax/TaxApiKeys.php
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Tax/TaxClasses.php
     * @magentoConfigFixture current_store tax/stripe_tax/prices_and_promotions_tax_behavior exclusive
     * @magentoConfigFixture current_store tax/stripe_tax/shipping_tax_behavior exclusive
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 0
     * @magentoConfigFixture current_store payment/stripe_payments/save_payment_method 0
     * @magentoConfigFixture current_store payment/stripe_payments/payment_action authorize_capture
     */
    public function testTaxExclusive()
    {
        $this->runTheTest('exclusive');
    }

    /**
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Tax/Test/Integration/_files/Data/Enable.php
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Tax/TaxApiKeys.php
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Tax/TaxClasses.php
     * @magentoConfigFixture current_store tax/stripe_tax/prices_and_promotions_tax_behavior inclusive
     * @magentoConfigFixture current_store tax/stripe_tax/shipping_tax_behavior inclusive
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 0
     * @magentoConfigFixture current_store payment/stripe_payments/save_payment_method 0
     * @magentoConfigFixture current_store payment/stripe_payments/payment_action authorize_capture
     */
    public function testTaxInclusive()
    {
        $this->runTheTest('inclusive');
    }

    private function runTheTest($taxBehaviour)
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("Simple")
            ->setShippingAddress("Romania")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("Romania")
            ->setPaymentMethod("SuccessCard");

        // Get product data
        $quoteItem = $this->quote->getQuoteItem('simple-product');
        $product = $this->productRepository->get($quoteItem->getSku());
        $price = $product->getPrice();
        $calculatedData = $this->calculator->calculateData($price, 2, 5, $taxBehaviour);

        // Compare quote data
        $this->compare->compareQuoteData($this->quote->getQuote(), $calculatedData);
        $quoteItemData = $this->calculator->calculateQuoteItemData($price, 10, 2, $taxBehaviour);
        $this->compare->compareQuoteItemData($quoteItem, $quoteItemData);

        // Compare order data
        $order = $this->quote->placeOrder();
        $order = $this->orderHelper->refreshOrder($order);
        $this->compare->compareOrderData($order, $calculatedData);
        $orderItem = $this->orderHelper->getOrderItem($order, 'simple-product');
        $this->compare->compareOrderItemData($orderItem, $quoteItemData);

        // Get invoice data and compare the invoice data
        \Magento\TestFramework\Helper\Bootstrap::getInstance()->loadArea('adminhtml');
        $order = $this->orderHelper->refreshOrder($order);
        $invoicesCollection = $order->getInvoiceCollection();
        $this->assertEquals(1, $invoicesCollection->getSize());
        $invoice = $invoicesCollection->getFirstItem();
        $this->compare->compareInvoiceData($invoice, $calculatedData);
        $invoiceItem = $this->invoiceHelper->getInvoiceItem($invoice, 'simple-product');
        $this->compare->compareInvoiceItemData($invoiceItem, $quoteItemData);

        // You should be able to create a credit memo
        $this->assertTrue($order->canCreditmemo());
        if ($taxBehaviour == 'exclusive') {
            $shipping = 10;
        } else {
            $shipping = 8.4;
        }

        // Try creating a credit memo with adjustments
        $orderGrandTotal = $order->getGrandTotal();
        // Create credit memo without shipping so that we don't get the error for not enough money
        $creditmemo = $this->tests->refundOnline($invoice, ['simple-product' => 2], 0, 5);

        // Get the reversal transaction and the totals on it
        $transaction = $this->stripeConfig->getStripeClient()->tax->transactions->retrieve($creditmemo->getStripeTaxTransactionId(), ['expand' => ['line_items']]);
        $transactionTotal = $this->creditmemoHelper->getTotalForTransaction($transaction, $taxBehaviour);

        // Expected total of the transaction should be the product price contained in the order
        // because we took out the shipping
        $tax = 0;
        if ($taxBehaviour  == 'exclusive') {
            $tax = $price * $this->calculator->getTaxRate() / 100;
        }
        $expectedTotal = ($price + $tax) * 2;
        $this->assertEquals($expectedTotal, $transactionTotal);
    }
}
