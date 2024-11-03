<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\Tax\SubscriptionInitialFee;

use StripeIntegration\Tax\Test\Integration\Helper\Calculator;

class MixedCartRefundTaxExclusiveTest extends BaseSubscription
{
    private $objectManager;
    private $quote;
    private $stripeConfig;
    private $stripeTaxConfig;
    private $tests;
    private $calculator;
    private $creditmemoHelper;

    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests($this);
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();
        $this->stripeConfig = $this->objectManager->get(\StripeIntegration\Payments\Model\Config::class);
        $this->stripeTaxConfig = $this->objectManager->get(\StripeIntegration\Tax\Model\Config::class);
        $this->calculator = new Calculator('Romania');
        $this->creditmemoHelper = new \StripeIntegration\Payments\Test\Integration\Helper\Creditmemo();
    }

    /**
     * magentoAppIsolation enabled
     *
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Tax/Test/Integration/_files/Data/Enable.php
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Tax/TaxApiKeys.php
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Tax/TaxClasses.php
     * @magentoConfigFixture current_store tax/stripe_tax/prices_and_promotions_tax_behavior exclusive
     * @magentoConfigFixture current_store tax/stripe_tax/shipping_tax_behavior exclusive
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 0
     * @magentoConfigFixture current_store payment/stripe_payments/save_payment_method 0
     * @magentoConfigFixture current_store payment/stripe_payments/payment_action authorize_capture
     */
    public function testTrialRefunds()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart('MixedCart')
            ->setShippingAddress("Romania")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("Romania")
            ->setPaymentMethod("SuccessCard");

        $order = $this->quote->placeOrder();
        $paymentIntent = $this->tests->confirmSubscription($order);

        // Refresh the order object
        $order = $this->tests->refreshOrder($order);

        $subscriptionDetails = [
            'sku' => 'simple-monthly-subscription-initial-fee-product',
            'qty' => 2,
            'price' => 10,
            'shipping' => 5,
            'initial_fee' => 3,
            'discount' => 0,
            'tax_percent' => $this->calculator->getTaxRate(),
            'mode' => 'exclusive'
        ];
        $this->compareSubscriptionDetails($order, $subscriptionDetails);

        $orderTotal = 78.54; // item at 10, shipping at 5, 15 * 1.19 = 17.85

        // Order checks
        $this->tests->compare($order->getData(), [
            "grand_total" => $orderTotal,
            "total_invoiced" => $orderTotal,
            "total_paid" => $orderTotal,
            "total_due" => 0,
            "total_refunded" => "unset",
            "total_canceled" => "unset",
            "state" => "processing",
            "status" => "processing",
        ]);
        $this->assertFalse($order->canCancel());
        $this->assertTrue($order->canCreditmemo());

        // Invoice checks
        $invoicesCollection = $order->getInvoiceCollection();
        $this->assertEquals(1, $invoicesCollection->count());
        $invoice = $invoicesCollection->getFirstItem();
        $this->assertEquals(\Magento\Sales\Model\Order\Invoice::STATE_PAID, $invoice->getState());
        $this->assertTrue($invoice->canRefund());

        // Stripe checks
        $stripe = $this->stripeConfig->getStripeClient();
        $customerId = $order->getPayment()->getAdditionalInformation("customer_stripe_id");
        $customer = $stripe->customers->retrieve($customerId);
        $this->assertEquals(1, count($customer->subscriptions->data));

        // Refund the order remainder
        $creditmemo = $this->tests->refundOnline($invoice, ['simple-product' => 2], $baseShipping = 10);

        // Refresh the order object
        $order = $this->tests->refreshOrder($order);

        // Order checks
        $this->tests->compare($order->getData(), [
            "grand_total" => $orderTotal,
            "total_invoiced" => $orderTotal,
            "total_paid" => $orderTotal,
            "total_due" => 0,
            "total_refunded" => 35.7,
            "total_canceled" => "unset",
            "state" => "processing",
            "status" => "processing",
        ]);

        $this->assertTrue($order->canCreditmemo());
        $this->assertTrue($invoice->canRefund());

        // Get the reversal transaction and the totals on it
        $transaction = $this->stripeTaxConfig->getStripeClient()->tax->transactions->retrieve($creditmemo->getStripeTaxTransactionId(), ['expand' => ['line_items']]);
        $transactionTotal = $this->creditmemoHelper->getTotalForTransaction($transaction, 'exclusive');

        $this->assertEquals(35.7, $transactionTotal);

        // Refund the order remainder
        $creditmemo = $this->tests->refundOnline($invoice, ['simple-monthly-subscription-initial-fee-product' => 2], $baseShipping = 10);

        // Refresh the order object
        $order = $this->tests->refreshOrder($order);

        // Order checks
        $this->tests->compare($order->getData(), [
            "grand_total" => $orderTotal,
            "total_invoiced" => $orderTotal,
            "total_paid" => $orderTotal,
            "total_due" => 0,
            "total_refunded" => $orderTotal,
            "total_canceled" => "unset",
            "state" => "closed",
            "status" => "closed",
        ]);

        $this->assertFalse($order->canCreditmemo());
        $this->assertFalse($invoice->canRefund());

        // Get the reversal transaction and the totals on it
        $transaction = $this->stripeTaxConfig->getStripeClient()->tax->transactions->retrieve($creditmemo->getStripeTaxTransactionId(), ['expand' => ['line_items']]);
        $transactionTotal = $this->creditmemoHelper->getTotalForTransaction($transaction, 'exclusive');

        $this->assertEquals(42.84, $transactionTotal);

        // Stripe checks
        $charges = $stripe->charges->all(['limit' => 10, 'customer' => $customer->id]);

        $expected = ['amount' => 7854, 'amount_captured' => 7854, 'amount_refunded' => 7854];

        $this->assertEquals($expected['amount'], $charges->data[0]->amount, "Charge");
        $this->assertEquals($expected['amount_captured'], $charges->data[0]->amount_captured, "Charge");
        $this->assertEquals($expected['amount_refunded'], $charges->data[0]->amount_refunded, "Charge");
    }
}