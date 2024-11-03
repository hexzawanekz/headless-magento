<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\CheckoutPage\EmbeddedFlow\AuthorizeCapture\ConfigurableTrialSubscription;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class PlaceOrderTest extends \PHPUnit\Framework\TestCase
{
    private $quote;
    private $tests;

    public function setUp(): void
    {
        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests($this);
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();
    }

    public function testPlaceOrder()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("ConfigurableTrialSubscription")
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setPaymentMethod("SuccessCard");

        $order = $this->quote->placeOrder();
        $this->tests->confirmSubscription($order);

        // Refresh the order object
        $order = $this->tests->refreshOrder($order);

        // Assert order status, amount due, invoices
        $this->assertEquals(0, $order->getGrandTotal());
        $this->assertEquals("processing", $order->getState());
        $this->assertEquals("processing", $order->getStatus());
        $this->assertEquals(1, $order->getInvoiceCollection()->count());

        // Check that the subscription plan amount is correct
        $customer = $this->tests->helper()->getCustomerModel()->retrieveByStripeID();
        $this->assertCount(1, $customer->subscriptions->data);
        $subscription = $customer->subscriptions->data[0];
        $this->assertEquals("trialing", $subscription->status);
        $this->assertEquals(0, $order->getGrandTotal());
        $trialSubscriptionTotal = 15.83;
        $trialSubscriptionTotalStripe = $this->tests->helper()->convertMagentoAmountToStripeAmount($trialSubscriptionTotal, $order->getOrderCurrencyCode());
        $this->assertEquals($trialSubscriptionTotalStripe, $subscription->plan->amount);

        // Assert order status, amount due, invoices, invoice items, invoice totals
        $this->tests->compare($order->getData(), [
            "state" => "processing",
            "status" => "processing",
            "total_due" => 0,
            "total_paid" => $order->getGrandTotal(),
            "total_refunded" => 0
        ]);

        // Credit memos check
        $this->assertEquals(0, $order->getCreditmemosCollection()->getSize());


        // Activate the subscription
        $ordersCount = $this->tests->getOrdersCount();
        $this->tests->endTrialSubscription($subscription->id);
        $newOrdersCount = $this->tests->getOrdersCount();
        $this->assertEquals($ordersCount + 1, $newOrdersCount);

        // Stripe checks
        $customer = $this->tests->stripe()->customers->retrieve($customer->id, ['expand' => ['subscriptions', 'subscriptions.data.plan']]);
        $this->assertNotEmpty($customer->subscriptions->data[0]->latest_invoice);
        $this->tests->compare($customer->subscriptions->data[0]->plan, [
            "amount" => 1583
        ]);

        $upcomingInvoice = $this->tests->stripe()->invoices->upcoming(['customer' => $customer->id]);
        $this->assertCount(1, $upcomingInvoice->lines->data);
        $this->tests->compare($upcomingInvoice, [
            "tax" => 0,
            "total" => 1583
        ]);

        // Process a recurring subscription billing webhook
        $invoice = $this->tests->stripe()->invoices->retrieve($customer->subscriptions->data[0]->latest_invoice);
        $this->tests->event()->trigger("invoice.payment_succeeded", $invoice, ['billing_reason' => 'subscription_cycle']);
        $newOrdersCount = $this->tests->getOrdersCount();
        $this->assertEquals($ordersCount + 2, $newOrdersCount);

        // Get the newly created order
        $newOrder = $this->tests->getLastOrder();

        // Assert new order, invoices, invoice items, invoice totals
        $this->assertNotEquals($order->getIncrementId(), $newOrder->getIncrementId());
        $this->assertEquals("processing", $newOrder->getState());
        $this->assertEquals("processing", $newOrder->getStatus());
        $this->assertEquals($trialSubscriptionTotal, $newOrder->getGrandTotal());
        $this->assertEquals(0, $newOrder->getTotalDue());
        $this->assertEquals(1, $newOrder->getInvoiceCollection()->getSize());
        $this->assertStringContainsString("pi_", $newOrder->getInvoiceCollection()->getFirstItem()->getTransactionId());

        // Stripe checks
        $invoice = $this->tests->stripe()->invoices->retrieve($customer->subscriptions->data[0]->latest_invoice, ['expand' => ['payment_intent']]);

        $this->tests->compare($invoice, [
            "payment_intent" => [
                "description" => "Recurring subscription order #{$newOrder->getIncrementId()} by Joyce Strother",
                "metadata" => [
                    "Order #" => $newOrder->getIncrementId()
                ]
            ]
        ]);
    }
}
