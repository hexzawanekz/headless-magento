<?php

namespace StripeIntegration\Payments\Test\Integration\StripeDashboard\EmbeddedFlow\AuthorizeOnly\ManualInvoicing\Normal;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class FullCaptureAndRefundTest extends \PHPUnit\Framework\TestCase
{
    private $quote;
    private $tests;

    public function setUp(): void
    {
        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests($this);
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();
    }

    /**
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 0
     * @magentoConfigFixture current_store payment/stripe_payments/payment_action authorize
     */
    public function testFullCaptureAndRefund()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("Normal")
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setPaymentMethod("SuccessCard");

        $order = $this->quote->placeOrder();
        $paymentIntent = $this->tests->confirm($order);

        // Refresh the order object
        $order = $this->tests->refreshOrder($order);
        $orderIncrementId = $order->getIncrementId();

        // Order checks
        $this->assertEquals(0, $order->getTotalPaid());
        $this->assertEquals(0, $order->getTotalRefunded());
        $this->assertEquals($order->getGrandTotal(), $order->getTotalDue());

        $invoicesCollection = $order->getInvoiceCollection();
        $this->assertCount(0, $invoicesCollection);

        // Full capture
        $paymentIntent = $this->tests->stripe()->paymentIntents->capture($paymentIntent->id);
        $this->assertEquals(5330, $paymentIntent->charges->data[0]->amount_captured);
        $this->tests->event()->trigger("charge.captured", $paymentIntent->charges->data[0]);
        $this->tests->event()->trigger("payment_intent.succeeded", $paymentIntent);

        // Refresh the order object
        $order = $this->tests->refreshOrder($order);

        // Invoice checks
        $invoicesCollection = $order->getInvoiceCollection();
        $this->assertCount(1, $invoicesCollection);
        $invoice = $invoicesCollection->getFirstItem();
        $this->assertEquals(\Magento\Sales\Model\Order\Invoice::STATE_PAID, $invoice->getState());

        // Fully refund the charge
        $refund = $this->tests->stripe()->refunds->create(['charge' => $paymentIntent->charges->data[0]]);
        $this->tests->event()->trigger("charge.refunded", $paymentIntent->charges->data[0]->id);

        // Refresh the order object
        $order = $this->tests->refreshOrder($order);
        $this->assertEquals("closed", $order->getStatus());
        $this->assertEquals($order->getGrandTotal(), $order->getTotalRefunded());
    }
}
