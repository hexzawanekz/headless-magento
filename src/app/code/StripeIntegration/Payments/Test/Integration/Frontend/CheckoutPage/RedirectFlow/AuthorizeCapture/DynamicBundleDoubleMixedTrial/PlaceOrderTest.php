<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\RedirectFlow\AuthorizeCapture\DynamicBundleDoubleMixedTrial;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class PlaceOrderTest extends \PHPUnit\Framework\TestCase
{
    private $objectManager;
    private $quote;
    private $subscriptions;
    private $tests;

    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests($this);
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();

        $this->subscriptions = $this->objectManager->get(\StripeIntegration\Payments\Helper\Subscriptions::class);
    }

    /**
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 1
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/ApiKeysLegacy.php
     */
    public function testDynamicBundleDoubleMixedTrialCart()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("DynamicBundleDoubleMixedTrial")
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setPaymentMethod("StripeCheckout");

        $quote = $this->quote->getQuote();

        // Checkout totals should be correct
        $trialSubscriptionsConfig = $this->subscriptions->getTrialingSubscriptionsAmounts($quote);

        // 4 subscriptions x $5 (50% off special price) + 4 products x $5 ($0% off)
        $this->assertEquals(40, $trialSubscriptionsConfig["subscriptions_total"], "Subtotal");
        $this->assertEquals(40, $trialSubscriptionsConfig["base_subscriptions_total"], "Base Subtotal");

        // 4 subscriptions x $5 and 50% off special price
        $this->assertEquals(10, $trialSubscriptionsConfig["shipping_total"], "Shipping");
        $this->assertEquals(10, $trialSubscriptionsConfig["base_shipping_total"], "Base Shipping");

        $this->assertEquals(0, $trialSubscriptionsConfig["discount_total"], "Discount");
        $this->assertEquals(0, $trialSubscriptionsConfig["base_discount_total"], "Base Discount");

        $this->assertEquals(3.30, $trialSubscriptionsConfig["tax_total"], "Tax");
        $this->assertEquals(3.30, $trialSubscriptionsConfig["tax_total"], "Base Tax");

        // Place the order
        $order = $this->quote->placeOrder();

        $ordersCount = $this->tests->getOrdersCount();

        // Assert order status, amount due, invoices
        $this->assertEquals("pending_payment", $order->getState());
        $this->assertEquals("pending_payment", $order->getStatus());
        $this->assertEquals(0, $order->getInvoiceCollection()->count());

        $paymentIntent = $this->tests->confirmCheckoutSession($order, "DynamicBundleDoubleMixedTrial", "card", "California");

        // Ensure that no new order was created
        $newOrdersCount = $this->tests->getOrdersCount();
        $this->assertEquals($ordersCount, $newOrdersCount);

        $orderIncrementId = $order->getIncrementId();
        $currency = $order->getOrderCurrencyCode();

        // Refresh the order
        $order = $this->tests->refreshOrder($order);

        // Check the order total
        $expectedOrderTotal = 0;
        $expectedOrderTotal += $bundleProductTotal = 2 * 4 * 5; /* Qty bundle = 2, Qty subproducts = 4, Discounted price = 5 */
        $expectedOrderTotal += $simpleProductTotal = 2 * 10; /* Qty simple product = 2 */
        $expectedOrderTotal += $bundleShippingTotal = 2 * 5; /* Bundle shipped together, so Qty bundle = 2 */
        $expectedOrderTotal += $simpleShippingTotal = 2 * 5; /* Qty simple product = 2 */
        $expectedOrderTotal += ($bundleTaxTotal = round($bundleProductTotal * 0.0825, 2)); // Shipping is not taxed
        $expectedOrderTotal += ($simpleTaxTotal = round($simpleProductTotal * 0.0825, 2)); // Shipping is not taxed

        // Trial subscriptions are reduced from the order total
        $expectedOrderTotal -= $trialSubscriptionTotal = $bundleProductTotal + $bundleShippingTotal - $trialSubscriptionsConfig["discount_total"] + $bundleTaxTotal;

        $expectedOrderTotal = round($expectedOrderTotal, 4);
        $this->assertEquals($expectedOrderTotal, $order->getGrandTotal());

        $customer = $this->tests->stripe()->customers->retrieve($paymentIntent->customer);
        $this->assertCount(1, $customer->subscriptions->data);
        $subscription = $customer->subscriptions->data[0];

        $expectedChargeAmountStripe = $this->tests->helper()->convertMagentoAmountToStripeAmount($expectedOrderTotal, $currency);
        $trialSubscriptionTotalStripe = $this->tests->helper()->convertMagentoAmountToStripeAmount($trialSubscriptionTotal, $currency);
        $this->assertEquals($trialSubscriptionTotalStripe, $subscription->plan->amount);

        // Assert order status, amount due, invoices, invoice items, invoice totals
        $this->tests->compare($order->getData(), [
            "state" => "processing",
            "status" => "processing",
            "total_due" => 0,
            "grand_total" => $expectedOrderTotal,
            "total_paid" => $order->getGrandTotal(),
            // "total_refunded" => $trialSubscriptionTotal
        ]);

        $this->assertEquals(1, $order->getInvoiceCollection()->getSize());
        $invoice = $order->getInvoiceCollection()->getFirstItem();
        $this->assertEquals(\Magento\Sales\Model\Order\Invoice::STATE_PAID, $invoice->getState());

        // Credit memos check
        $this->assertEquals(0, $order->getCreditmemosCollection()->getSize());

        // Retrieve the created session
        $checkoutSessionId = $order->getPayment()->getAdditionalInformation('checkout_session_id');
        $this->assertNotEmpty($checkoutSessionId);

        $stripe = $this->tests->stripe();
        $session = $stripe->checkout->sessions->retrieve($checkoutSessionId);

        $this->assertEquals($expectedChargeAmountStripe, $session->amount_total);

        // Stripe subscription checks
        $customer = $stripe->customers->retrieve($session->customer);
        $this->assertCount(1, $customer->subscriptions->data);
        $subscription = $customer->subscriptions->data[0];
        $this->assertEquals("trialing", $subscription->status);

        $subscriptionId = $subscription->id;

        // End the trial
        $this->tests->endTrialSubscription($subscriptionId);
        $order = $this->tests->refreshOrder($order);

        // Check that a new order was created
        $newOrdersCount = $this->tests->getOrdersCount();
        $this->assertEquals($ordersCount + 1, $newOrdersCount);

        // Check the newly created order
        $newOrder = $this->tests->getLastOrder();
        $this->assertNotEquals($order->getIncrementId(), $newOrder->getIncrementId());
        $order = $newOrder;

        $this->tests->compare($order->getData(), [
            "state" => "processing",
            "status" => "processing",
            "total_due" => 0,
            "grand_total" => $trialSubscriptionTotal,
            "shipping_amount" => $trialSubscriptionsConfig["shipping_total"],
            "total_paid" => $order->getGrandTotal(),
            "total_refunded" => 0
        ]);
        $this->assertEquals(1, $order->getInvoiceCollection()->getSize());
        $invoice = $order->getInvoiceCollection()->getFirstItem();
        $this->assertEquals(\Magento\Sales\Model\Order\Invoice::STATE_PAID, $invoice->getState());

        // Process a recurring subscription billing webhook
        $subscription = $this->tests->stripe()->subscriptions->retrieve($subscriptionId, []);
        $this->tests->event()->trigger("invoice.payment_succeeded", $subscription->latest_invoice, ['billing_reason' => 'subscription_cycle']);

        // Get the newly created order
        $newOrder = $this->tests->getLastOrder();
        $this->assertNotEquals($order->getIncrementId(), $newOrder->getIncrementId());
        $order = $newOrder;

        $this->tests->compare($order->getData(), [
            "state" => "processing",
            "status" => "processing",
            "total_due" => 0,
            "grand_total" => $trialSubscriptionTotal,
            "shipping_amount" => $trialSubscriptionsConfig["shipping_total"],
            "total_paid" => $order->getGrandTotal(),
            "total_refunded" => 0
        ]);

        $this->assertEquals(1, $order->getInvoiceCollection()->count());
        $this->assertEquals(0, $order->getCreditmemosCollection()->count());
    }
}
