<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\Tax;

use StripeIntegration\Payments\Test\Integration\Helper\CreditMemoCompare;
use StripeIntegration\Tax\Test\Integration\Helper\Calculator;
use StripeIntegration\Tax\Test\Integration\Helper\Compare;

class PartialCreditMemosTest extends \PHPUnit\Framework\TestCase
{
    private $quote;
    private $compare;
    private $calculator;
    private $tests;
    private $orderHelper;
    private $invoiceHelper;
    private $creditmemoCompareHelper;
    private $creditmemoHelper;

    public function setUp(): void
    {
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();
        $this->compare = new Compare($this);
        $this->calculator = new Calculator('Romania');
        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests($this);
        $this->orderHelper = new \StripeIntegration\Tax\Test\Integration\Helper\Order();
        $this->invoiceHelper = new \StripeIntegration\Tax\Test\Integration\Helper\Invoice();
        $this->creditmemoCompareHelper = new CreditMemoCompare($this);
        $this->creditmemoHelper = new \StripeIntegration\Payments\Test\Integration\Helper\Creditmemo();
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
            ->setCart("ThreePartialCreditMemos")
            ->setShippingAddress("Romania")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("Romania")
            ->setPaymentMethod("SuccessCard");

        // Calculate total data for quote, bundle product = 100 (20 initial + 2*20 + 2*20), configurable product = 10, virtual product = 10
        // Shipping for the products is 5 for bundle and configurable and 0 for virtual
        $calculatedData = $this->calculator->calculateData(100 + 10 + 10, 1, 5 + 5, $taxBehaviour);
        $this->compare->compareQuoteData($this->quote->getQuote(), $calculatedData);

        // Compare data for the quote items
        $quoteItem = $this->quote->getQuoteItem('simple-product');
        $configurableItemData = $this->calculator->calculateQuoteItemData(10, 10, 1, $taxBehaviour);
        $this->compare->compareQuoteItemData($quoteItem, $configurableItemData);

        $quoteItem = $this->quote->getQuoteItem('virtual-product');
        $virtualItemData = $this->calculator->calculateQuoteItemData(10, 0, 1, $taxBehaviour);
        $this->compare->compareQuoteItemData($quoteItem, $virtualItemData);

        $quoteItem = $this->quote->getQuoteItem('bundle-fixed-no-subscriptions-simple-product-virtual-product');
        $bundleItemData = $this->calculator->calculateQuoteItemData(100, 10, 1, $taxBehaviour);
        $this->compare->compareQuoteItemData($quoteItem, $bundleItemData);

        // Place order and compare order data
        $order = $this->quote->placeOrder();
        $order = $this->orderHelper->refreshOrder($order);
        $orderGrandTotal = $order->getGrandTotal();
        $this->compare->compareOrderData($order, $calculatedData);

        $orderItem = $this->orderHelper->getOrderItem($order, 'simple-product');
        $this->compare->compareOrderItemData($orderItem, $configurableItemData);
        $orderItem = $this->orderHelper->getOrderItem($order, 'virtual-product');
        $this->compare->compareOrderItemData($orderItem, $virtualItemData);
        $orderItem = $this->orderHelper->getOrderItem($order, 'bundle-fixed-no-subscriptions-simple-product-virtual-product');
        $this->compare->compareOrderItemData($orderItem, $bundleItemData);

        // Get invoice data and compare the invoice data
        \Magento\TestFramework\Helper\Bootstrap::getInstance()->loadArea('adminhtml');
        $order = $this->orderHelper->refreshOrder($order);
        $invoicesCollection = $order->getInvoiceCollection();
        $this->assertEquals(1, $invoicesCollection->getSize());
        $invoice = $invoicesCollection->getFirstItem();
        $this->compare->compareInvoiceData($invoice, $calculatedData);

        $invoiceItem = $this->invoiceHelper->getInvoiceItem($invoice, 'simple-product');
        $this->compare->compareInvoiceItemData($invoiceItem, $configurableItemData);
        $invoiceItem = $this->invoiceHelper->getInvoiceItem($invoice, 'virtual-product');
        $this->compare->compareOrderItemData($invoiceItem, $virtualItemData);
        $invoiceItem = $this->invoiceHelper->getInvoiceItem($invoice, 'bundle-fixed-no-subscriptions-simple-product-virtual-product');
        $this->compare->compareOrderItemData($invoiceItem, $bundleItemData);

        // You should be able to create
        $this->assertTrue($order->canCreditmemo());

        if ($taxBehaviour == 'exclusive') {
            $shippingWithoutTax = 10;
            $shippingWithTax = 11.9;
            $configurablePrice = 11.9;
        } else {
            $shippingWithoutTax = 8.4;
            $shippingWithTax = 10;
            $configurablePrice = 10;
        }
        $creditmemo = $this->tests->refundOnline($invoice, ['simple-product' => 1], $shippingWithoutTax);
        $order = $this->tests->refreshOrder($order);
        $creditmemoData = $this->calculator->calculateData(10, 1, 10, $taxBehaviour);
        $creditmemoItemData = $this->calculator->calculateQuoteItemData(10, 10, 1, $taxBehaviour);
        // Compare credit memo data
        $this->creditmemoCompareHelper->compareCreditmemoData($creditmemo, $creditmemoData);
        $creditmemoItem = $this->creditmemoHelper->getCreditmemoItem($creditmemo, 'simple-product');
        $this->creditmemoCompareHelper->compareCreditMemoItemData($creditmemoItem, $creditmemoItemData);
        // Refunded total should be the price of the configurable product + the shipping for the whole order
        $refunded1 = $configurablePrice + $shippingWithTax;
        $this->tests->compare($order->getData(), [
            "total_invoiced" => $orderGrandTotal,
            "total_paid" => $orderGrandTotal,
            "total_due" => 0,
            "total_refunded" => $refunded1,
            "total_canceled" => 0,
            "state" => "processing",
            "status" => "processing"
        ]);
        // You can still create credit memos for the order
        $this->assertTrue($order->canCreditmemo());

        if ($taxBehaviour == 'exclusive') {
            $virtualPrice = 11.9;
        } else {
            $virtualPrice = 10;
        }

        $creditmemo = $this->tests->refundOnline($invoice, ['virtual-product' => 1], 0);
        $order = $this->tests->refreshOrder($order);
        $creditmemoData = $this->calculator->calculateData(10, 1, 0, $taxBehaviour);
        $creditmemoItemData = $this->calculator->calculateQuoteItemData(10, 0, 1, $taxBehaviour);
        // Compare credit memo data
        $this->creditmemoCompareHelper->compareCreditmemoData($creditmemo, $creditmemoData);
        $creditmemoItem = $this->creditmemoHelper->getCreditmemoItem($creditmemo, 'virtual-product');
        $this->creditmemoCompareHelper->compareCreditMemoItemData($creditmemoItem, $creditmemoItemData);
        // Refunded total should be the previous refund + the price of the current item
        $refunded2 = $refunded1 + $virtualPrice;
        $this->tests->compare($order->getData(), [
            "total_invoiced" => $orderGrandTotal,
            "total_paid" => $orderGrandTotal,
            "total_due" => 0,
            "total_refunded" => $refunded2,
            "total_canceled" => 0,
            "state" => "processing",
            "status" => "processing"
        ]);
        // You can still create credit memos for the order
        $this->assertTrue($order->canCreditmemo());

        $creditmemo = $this->tests->refundOnline($invoice, ['bundle-fixed-no-subscriptions-simple-product-virtual-product' => 1], 0);
        $order = $this->tests->refreshOrder($order);
        $creditmemoData = $this->calculator->calculateData(100, 1, 0, $taxBehaviour);
        $creditmemoItemData = $this->calculator->calculateQuoteItemData(100, 0, 1, $taxBehaviour);
        // Compare credit memo data
        $this->creditmemoCompareHelper->compareCreditmemoData($creditmemo, $creditmemoData);
        $creditmemoItem = $this->creditmemoHelper->getCreditmemoItem($creditmemo, 'bundle-fixed-no-subscriptions-simple-product-virtual-product');
        $this->creditmemoCompareHelper->compareCreditMemoItemData($creditmemoItem, $creditmemoItemData);
        // You should not be able to create credit memos for the order
        $this->assertFalse($order->canCreditmemo());

        $this->tests->compare($order->getData(), [
            "total_invoiced" => $orderGrandTotal,
            "total_paid" => $orderGrandTotal,
            "total_due" => 0,
            "total_refunded" => $orderGrandTotal,
            "total_canceled" => 0,
            "state" => "closed",
            "status" => "closed"
        ]);
    }
}