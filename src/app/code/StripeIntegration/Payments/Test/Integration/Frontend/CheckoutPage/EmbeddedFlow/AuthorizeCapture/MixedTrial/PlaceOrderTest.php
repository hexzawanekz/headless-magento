<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\CheckoutPage\EmbeddedFlow\AuthorizeCapture\MixedTrial;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class PlaceOrderTest extends \PHPUnit\Framework\TestCase
{
    private $objectManager;
    private $quote;
    private $tests;
    private $orderHelper;

    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests($this);
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();
        $this->orderHelper = $this->objectManager->create(\StripeIntegration\Payments\Helper\Order::class);
    }

    /**
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 0
     * @magentoConfigFixture current_store payment/stripe_payments/payment_action authorize_capture
     */
    public function testPlaceOrder()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("MixedTrial")
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setPaymentMethod("SuccessCard");

        $ordersCount = $this->tests->getOrdersCount();

        $order = $this->quote->placeOrder();
        $subscriptionId = $order->getPayment()->getAdditionalInformation("subscription_id");
        $this->tests->event()->triggerSubscriptionEventsById($subscriptionId);

        // Create the payment info block for $order
        $paymentInfoBlock = $this->objectManager->create(\StripeIntegration\Payments\Block\PaymentInfo\Element::class);
        $paymentInfoBlock->setOrder($order);
        $paymentInfoBlock->setInfo($order->getPayment());

        // Test the payment info block
        $paymentMethod = $paymentInfoBlock->getPaymentMethod();
        $formattedAmount = $paymentInfoBlock->getFormattedAmount();
        $paymentStatus = $paymentInfoBlock->getPaymentStatus();
        $isStripeMethod = $paymentInfoBlock->isStripeMethod();
        $paymentIntent = $paymentInfoBlock->getPaymentIntent();
        $subscription = $paymentInfoBlock->getSubscription();
        $setupIntent = $paymentInfoBlock->getSetupIntent();
        $subscriptionOrderUrl = $paymentInfoBlock->getSubscriptionOrderUrl($order->getIncrementId());
        $formattedSubscriptionAmount = (string)$paymentInfoBlock->getFormattedSubscriptionAmount();
        $customerId = $paymentInfoBlock->getCustomerId();

        $this->assertNotEmpty($paymentIntent);
        $this->assertNotEmpty($paymentMethod);
        $this->assertNotEmpty($subscription);

        $this->assertStringStartsWith("pm_", $paymentMethod->id);
        $this->assertEquals("$15.83", $formattedAmount);
        $this->assertEquals("succeeded", $paymentStatus);
        $this->assertTrue($isStripeMethod);
        $this->assertStringStartsWith("pi_", $paymentIntent->id);
        $this->assertStringStartsWith("sub_", $subscription->id);
        $this->assertEmpty($setupIntent);
        $this->assertStringStartsWith("http", $subscriptionOrderUrl);
        $this->assertStringEndsWith($order->getId() . "/", $subscriptionOrderUrl);
        $this->assertStringStartsWith("cus_", $customerId);
        $this->assertEquals("$15.83 every month", $formattedSubscriptionAmount);

        // Refresh the order object
        $order = $this->tests->refreshOrder($order);

        $stripe = $this->tests->stripe();

        // Check Stripe objects
        $customerId = $order->getPayment()->getAdditionalInformation("customer_stripe_id");
        $customer = $stripe->customers->retrieve($customerId);
        $this->assertEquals(1, count($customer->subscriptions->data));
        $subscription = $customer->subscriptions->data[0];

        $this->assertNotEmpty($subscription->latest_invoice);
        $invoiceId = $subscription->latest_invoice;

        $invoice = $stripe->invoices->retrieve($invoiceId, ['expand' => ['charge', 'payment_intent']]);
        $this->assertNotEmpty($invoice->subscription);
        $subscriptionId = $invoice->subscription;
        $paymentIntentId = $invoice->payment_intent->id;

        // The one time payment is $15.83
        $this->tests->compare($invoice, [
            "total" => 1583,
            "amount_due" => 1583,
            "amount_paid" => 1583,
            "amount_remaining" => 0,
            "payment_intent" => [
                "description" => $this->orderHelper->getOrderDescription($order)
            ]
        ]);

        // Ensure that after the webhooks, no new order was created
        $newOrdersCount = $this->tests->getOrdersCount();
        $this->assertEquals($ordersCount + 1, $newOrdersCount);

        $this->assertEquals("processing", $order->getState());
        $this->assertEquals("processing", $order->getStatus());

        // Check that an invoice was created
        $invoicesCollection = $order->getInvoiceCollection();
        $this->assertNotEmpty($invoicesCollection);
        $this->assertEquals(1, $invoicesCollection->getSize());

        $invoice = $invoicesCollection->getFirstItem();
        $this->assertCount(2, $invoice->getAllItems());
        $this->assertEquals(\Magento\Sales\Model\Order\Invoice::STATE_PAID, $invoice->getState());
        $this->assertEquals($paymentIntentId, $invoice->getTransactionId());
        $this->tests->compare($order->getData(), [
            "grand_total" => 15.83,
            "total_paid" => $order->getGrandTotal(),
            "base_total_paid" => $order->getBaseGrandTotal(),
            "total_refunded" => "unset",
            "base_total_refunded" => "unset"
        ]);

        // Check that the transaction IDs have been associated with the order
        $transactions = $this->tests->helper()->getOrderTransactions($order);
        $this->assertEquals(1, count($transactions));
        foreach ($transactions as $key => $transaction)
        {
            $this->assertEquals($paymentIntentId, $transaction->getTxnId());
            $this->assertContains($transaction->getTxnType(), ["capture", "refund"]);
        }

        // End the trial
        $ordersCount = $this->tests->getOrdersCount();
        $subscription = $this->tests->endTrialSubscription($subscriptionId);

        // Ensure that a new order was created
        $newOrdersCount = $this->tests->getOrdersCount();
        $this->assertEquals($ordersCount + 1, $newOrdersCount);

        // Check that the transaction IDs has NOT been associated with the old order
        $transactions = $this->tests->helper()->getOrderTransactions($order);
        $this->assertEquals(1, count($transactions));

        // Check the newly created order
        $newOrder = $this->tests->getLastOrder();
        $this->assertNotEquals($order->getIncrementId(), $newOrder->getIncrementId());
        $this->assertEquals("processing", $newOrder->getState());
        $this->assertEquals("processing", $newOrder->getStatus());
        $this->assertEquals(15.83, $newOrder->getGrandTotal());
        $this->assertEquals(15.83, $newOrder->getTotalPaid());
        $this->assertEquals(1, $newOrder->getInvoiceCollection()->getSize());
        $invoice = $newOrder->getInvoiceCollection()->getFirstItem();
        $this->assertEquals(\Magento\Sales\Model\Order\Invoice::STATE_PAID, $invoice->getState());
    }
}
