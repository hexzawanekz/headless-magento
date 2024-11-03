<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\CheckoutPage\EmbeddedFlow\AuthorizeCapture;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class RefundsTest extends \PHPUnit\Framework\TestCase
{
    private $objectManager;
    private $quote;
    private $stripeConfig;
    private $tests;

    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests($this);
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();
        $this->stripeConfig = $this->objectManager->get(\StripeIntegration\Payments\Model\Config::class);
    }

    /**
     * magentoAppIsolation enabled
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 0
     * @magentoConfigFixture current_store payment/stripe_payments/payment_action authorize_capture
     */
    public function testTrialRefunds()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart('MixedTrial')
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setPaymentMethod("SuccessCard");

        $order = $this->quote->placeOrder();
        $paymentIntent = $this->tests->confirmSubscription($order);

        // Refresh the order object
        $order = $this->tests->refreshOrder($order);

        // Order checks
        $this->tests->compare($order->getData(), [
            "grand_total" => 15.83,
            "total_invoiced" => $order->getGrandTotal(),
            "total_paid" => $order->getGrandTotal(),
            "total_due" => 0,
            "total_refunded" => "unset",
            "total_canceled" => "unset",
            "state" => "processing",
            "status" => "processing",
        ]);
        $this->assertFalse($order->canCancel());
        $this->assertTrue($order->canCreditmemo()); // Because Simple Product was paid

        // Invoice checks
        $invoicesCollection = $order->getInvoiceCollection();
        $this->assertEquals(1, $invoicesCollection->count());
        $invoice = $invoicesCollection->getFirstItem();
        $this->assertEquals(\Magento\Sales\Model\Order\Invoice::STATE_PAID, $invoice->getState());
        $this->assertFalse($invoice->canCancel());
        $this->assertFalse($invoice->canCapture()); // Offline capture should be possible

        // Stripe checks
        $stripe = $this->stripeConfig->getStripeClient();
        $customerId = $order->getPayment()->getAdditionalInformation("customer_stripe_id");
        $customer = $stripe->customers->retrieve($customerId);
        $this->assertEquals(1, count($customer->subscriptions->data));

        // Expire the trial subscription
        $ordersCount = $this->tests->getOrdersCount();
        foreach ($customer->subscriptions->data as $subscription)
        {
            $this->tests->endTrialSubscription($subscription->id);
        }

        // Check that a new order was created
        $newOrdersCount = $this->tests->getOrdersCount();
        $this->assertEquals($ordersCount + 1, $newOrdersCount);

        // Refresh the order object
        $order = $this->tests->refreshOrder($order);

        // Order checks
        $this->tests->compare($order->getData(), [
            "grand_total" => 15.83,
            "total_invoiced" => $order->getGrandTotal(),
            "total_paid" => $order->getGrandTotal(),
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
        $this->assertFalse($invoice->canCancel());
        $this->assertFalse($invoice->canCapture());
        $this->assertTrue($invoice->canRefund());

        // Refund the order remainder
        $this->tests->refundOnline($invoice, ['simple-product' => 1], $baseShipping = 5);

        // Refresh the order object
        $order = $this->tests->refreshOrder($order);

        // Order checks
        $this->tests->compare($order->getData(), [
            "grand_total" => 15.83,
            "total_invoiced" => $order->getGrandTotal(),
            "total_paid" => $order->getGrandTotal(),
            "total_due" => 0,
            "total_refunded" => $order->getGrandTotal(),
            "total_canceled" => "unset",
            "state" => "processing",
            "status" => "processing",
        ]);

        // Refund the trial subscription
        $newOrder = $this->tests->getLastOrder();
        $this->assertNotEquals($order->getIncrementId(), $newOrder->getIncrementId());
        $this->assertTrue($newOrder->canCreditmemo());
        $invoice = $newOrder->getInvoiceCollection()->getFirstItem();
        $this->tests->refundOnline($invoice, ['simple-trial-monthly-subscription-product' => 1], $baseShipping = 5);

        // Refresh the order object
        $order = $this->tests->refreshOrder($newOrder);

        // Order checks
        $this->tests->compare($order->getData(), [
            "grand_total" => 15.83,
            "total_invoiced" => $order->getGrandTotal(),
            "total_paid" => $order->getGrandTotal(),
            "total_due" => 0,
            "total_refunded" => $order->getGrandTotal(),
            "total_canceled" => "unset",
            "state" => "closed",
            "status" => "closed",
        ]);
        $this->assertFalse($order->canCreditmemo());

        // Stripe checks
        $charges = $stripe->charges->all(['limit' => 10, 'customer' => $customer->id]);

        $expected = [
            ['amount' => 1583, 'amount_captured' => 1583, 'amount_refunded' => 1583],
            ['amount' => 1583, 'amount_captured' => 1583, 'amount_refunded' => 1583],
        ];

        for ($i = 0; $i < count($charges); $i++)
        {
            $this->assertEquals($expected[$i]['amount'], $charges->data[$i]->amount, "Charge $i");
            $this->assertEquals($expected[$i]['amount_captured'], $charges->data[$i]->amount_captured, "Charge $i");
            $this->assertEquals($expected[$i]['amount_refunded'], $charges->data[$i]->amount_refunded, "Charge $i");
        }
    }
}
