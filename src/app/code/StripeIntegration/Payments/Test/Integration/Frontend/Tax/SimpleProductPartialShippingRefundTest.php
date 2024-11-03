<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\Tax;

use StripeIntegration\Payments\Test\Integration\Helper\CreditMemoCompare;
use StripeIntegration\Tax\Test\Integration\Helper\Calculator;
use StripeIntegration\Tax\Test\Integration\Helper\Compare;
class SimpleProductPartialShippingRefundTest extends \PHPUnit\Framework\TestCase
{
    private $objectManager;
    private $quote;
    private $compare;
    private $calculator;
    private $productRepository;
    private $tests;
    private $orderHelper;
    private $invoiceHelper;
    private $creditmemoCompareHelper;
    private $creditmemoHelper;

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

        // Create credit memo containing partial shipment
        $this->assertTrue($order->canCreditmemo());
        if ($taxBehaviour == 'exclusive') {
            $shipping1 = 7;
        } else {
            $shipping1 = 5;
        }
        $orderGrandTotal = $order->getGrandTotal();

        // Compare data for credit memo containing partial shipment
        $creditmemo = $this->tests->refundOnline($invoice, ['simple-product' => 0], $shipping1);
        $order = $this->tests->refreshOrder($order);
        // The calculation mode is exclusive regardless, because the shipping which can be set for the
        // credit memo does not contain the tax, and it will be calculated on top regardless
        $creditmemoData = $this->calculator->calculateData(0, 1, $shipping1, 'exclusive');
        $this->creditmemoCompareHelper->compareCreditmemoData($creditmemo, $creditmemoData);
        $this->tests->compare($order->getData(), [
            "total_invoiced" => $orderGrandTotal,
            "total_paid" => $orderGrandTotal,
            "total_due" => 0,
            "total_refunded" => round($shipping1 + $this->calculator->getTaxRate() / 100 * $shipping1, 2),
            "total_canceled" => 0,
            "state" => "processing",
            "status" => "processing"
        ]);
        $this->assertTrue($order->canCreditmemo());

        // Create another credit memo with te remaining shipping
        if ($taxBehaviour == 'exclusive') {
            $shipping2 = 3;
        } else {
            $shipping2 = 3.4;
        }
        $creditmemo = $this->tests->refundOnline($invoice, ['simple-product' => 0], $shipping2);
        $order = $this->tests->refreshOrder($order);
        $creditmemoData = $this->calculator->calculateData(0, 1, $shipping2, 'exclusive');
        $this->creditmemoCompareHelper->compareCreditmemoData($creditmemo, $creditmemoData);
        // The total refunded will be the whole shipping sum for the order
        $this->tests->compare($order->getData(), [
            "total_invoiced" => $orderGrandTotal,
            "total_paid" => $orderGrandTotal,
            "total_due" => 0,
            "total_refunded" => round($shipping1 + $shipping2 + $this->calculator->getTaxRate() / 100 * ($shipping1 + $shipping2), 2),
            "total_canceled" => 0,
            "state" => "processing",
            "status" => "processing"
        ]);
        // You can still create credit memos for the order
        $this->assertTrue($order->canCreditmemo());

        // Create credit memo for the order item
        $creditmemo = $this->tests->refundOnline($invoice, ['simple-product' => 2]);
        $order = $this->tests->refreshOrder($order);
        $creditmemoData = $this->calculator->calculateData($price, 2, 0, $taxBehaviour);
        $this->creditmemoCompareHelper->compareCreditmemoData($creditmemo, $creditmemoData);
        $creditmemoItemData = $this->calculator->calculateQuoteItemData($price, 0, 2, $taxBehaviour);
        $creditmemoItem = $this->creditmemoHelper->getCreditmemoItem($creditmemo, 'simple-product');
        $this->creditmemoCompareHelper->compareCreditMemoItemData($creditmemoItem, $creditmemoItemData);

        // Compare the order data after credit memos
        $this->tests->compare($order->getData(), [
            "total_invoiced" => $orderGrandTotal,
            "total_paid" => $orderGrandTotal,
            "total_due" => 0,
            "total_refunded" => $orderGrandTotal,
            "total_canceled" => 0,
            "state" => "closed",
            "status" => "closed"
        ]);
        // You should not be able to create credit memos on the order
        $this->assertFalse($order->canCreditmemo());
    }
}