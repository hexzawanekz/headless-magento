<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\CheckoutPage\EmbeddedFlow\AuthorizeCapture\MixedTrial;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class MultiCurrencyRefundsTest extends \PHPUnit\Framework\TestCase
{
    private $quote;
    private $tests;

    public function setUp(): void
    {
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();
        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests($this);
    }

    /**
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 0
     * @magentoConfigFixture current_store payment/stripe_payments/payment_action authorize_capture
     *
     * @magentoConfigFixture current_store currency/options/base USD
     * @magentoConfigFixture current_store currency/options/allow EUR,USD
     * @magentoConfigFixture current_store currency/options/default EUR
     */
    public function testRefunds()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("MixedTrial")
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setPaymentMethod("SuccessCard");

        $order = $this->quote->placeOrder();
        $paymentIntent = $this->tests->confirmSubscription($order);

        // Refresh the order object
        $order = $this->tests->refreshOrder($order);

        // Invoice checks
        $invoicesCollection = $order->getInvoiceCollection();
        $this->assertEquals(1, $invoicesCollection->getSize());
        $invoice = $invoicesCollection->getFirstItem();
        $this->assertEquals(\Magento\Sales\Model\Order\Invoice::STATE_PAID, $invoice->getState());

        // Order checks
        $this->tests->compare($order->debug(), [
            "base_grand_total" => 15.83,
            "grand_total" => 13.45,
            "base_total_invoiced" => 15.83,
            "total_invoiced" => 13.45,
            "base_total_paid" => 15.83,
            "total_paid" => 13.45,
            "base_total_due" => 0,
            "total_due" => 0,
            "total_refunded" => "unset",
            "total_canceled" => "unset",
            "state" => "processing",
            "status" => "processing"
        ]);

        // Credit memo checks
        $creditmemoCollection = $order->getCreditmemosCollection();
        $this->assertEquals(0, $creditmemoCollection->getSize());

        // Invoice checks
        $invoicesCollection = $order->getInvoiceCollection();
        $this->assertEquals(1, $invoicesCollection->getSize());
        $invoice = $invoicesCollection->getFirstItem();
        $this->assertEquals(\Magento\Sales\Model\Order\Invoice::STATE_PAID, $invoice->getState());

        // Stripe checks
        $stripe = $this->tests->stripe();
        $customerId = $order->getPayment()->getAdditionalInformation("customer_stripe_id");
        $customer = $stripe->customers->retrieve($customerId);
        $this->assertEquals(1, count($customer->subscriptions->data));

        $charges = $stripe->charges->all(['limit' => 10, 'customer' => $customer->id]);
        $charge = $charges->data[0];
        $this->tests->compare($charge, [
            "amount" => 1345,
            "amount_captured" => 1345,
            "amount_refunded" => 0,
            "currency" => "eur"
        ]);

        // Expire the trial subscription
        $ordersCount = $this->tests->getOrdersCount();
        $subscription = $this->tests->endTrialSubscription($customer->subscriptions->data[0]->id);

        // Refresh the order object
        $order = $this->tests->refreshOrder($order);

        // Check that a new order was created
        $newOrdersCount = $this->tests->getOrdersCount();
        $this->assertEquals($ordersCount + 1, $newOrdersCount);
        $newOrder = $this->tests->getLastOrder();

        // New order checks
        $this->tests->compare($newOrder->debug(), [
            "base_grand_total" => 15.83,
            "grand_total" => 13.45,
            "base_total_invoiced" => 15.83,
            "total_invoiced" => 13.45,
            "base_total_paid" => 15.83,
            "total_paid" => 13.45,
            "base_total_due" => 0,
            "total_due" => 0,
            "total_refunded" => "unset",
            "total_canceled" => "unset",
            "state" => "processing",
            "status" => "processing"
        ]);

        // Refund the original order
        $this->assertTrue($order->canCreditmemo());
        $this->tests->refundOnline($invoice, ['simple-product' => 1], $baseShipping = 5);

        // Refresh the order object
        $order = $this->tests->refreshOrder($order);

        $this->tests->compare($order->debug(), [
            "base_total_refunded" => 15.83,
            "total_refunded" => 13.45,
            "total_canceled" => "unset",
            "state" => "processing",
            "status" => "processing"
        ]);

        // Refund the trial subscription via the 2nd order
        $oldIncrementId = $order->getIncrementId();
        $this->assertNotEquals($oldIncrementId, $newOrder->getIncrementId());
        $this->assertTrue($newOrder->canCreditmemo());
        $this->assertEquals(0, $newOrder->getCreditmemosCollection()->getSize());
        $invoice = $newOrder->getInvoiceCollection()->getFirstItem();

        if ($this->tests->magento("<", "2.4"))
        {
            // Magento 2.3.7-p3 does not perform a currency conversion on the tax_amount
            $this->expectExceptionMessage("Could not refund payment: Requested a refund of €13.58, but the most amount that can be refunded online is €13.45.");
        }

        $this->tests->refundOnline($invoice, ['simple-trial-monthly-subscription-product' => 1], $baseShipping = 5);

        // Refresh the order object
        $newOrder = $this->tests->refreshOrder($newOrder);

        // Order checks
        $this->tests->compare($newOrder->debug(), [
            "base_total_refunded" => 15.83,
            "total_refunded" => 13.45,
            "total_canceled" => "unset",
            "state" => "closed",
            "status" => "closed"
        ]);

        $this->assertFalse($newOrder->canCreditmemo()); // @todo: inverse rounding error, should be false

        // Stripe checks
        $charges = $stripe->charges->all(['limit' => 10, 'customer' => $customer->id]);

        $expected = [
            ['amount' => 1345, 'amount_captured' => 1345, 'amount_refunded' => 1345, 'currency' => 'eur'],
            ['amount' => 1345, 'amount_captured' => 1345, 'amount_refunded' => 1345, 'currency' => 'eur'],
        ];

        for ($i = 0; $i < count($charges); $i++)
        {
            $this->assertEquals($expected[$i]['currency'], $charges->data[$i]->currency, "Charge $i");
            $this->assertEquals($expected[$i]['amount'], $charges->data[$i]->amount, "Charge $i");
            $this->assertEquals($expected[$i]['amount_captured'], $charges->data[$i]->amount_captured, "Charge $i");
            $this->assertEquals($expected[$i]['amount_refunded'], $charges->data[$i]->amount_refunded, "Charge $i");
        }
    }
}
