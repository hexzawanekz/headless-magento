<?php

namespace StripeIntegration\Payments\Test\Integration\StripeDashboard\EmbeddedFlow\AuthorizeOnly\ManualInvoicing\Normal;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class PartialCaptureTest extends \PHPUnit\Framework\TestCase
{
    private $helper;
    private $objectManager;
    private $quote;
    private $tests;

    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();
        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests($this);

        $this->helper = $this->objectManager->get(\StripeIntegration\Payments\Helper\Generic::class);
    }

    /**
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 0
     * @magentoConfigFixture current_store payment/stripe_payments/payment_action authorize
     */
    public function testPartialCapture()
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

        $currency = $order->getOrderCurrencyCode();
        $amount = $this->helper->convertMagentoAmountToStripeAmount($order->getGrandTotal(), $currency);

        $this->assertEquals("processing", $order->getStatus());
        $this->assertEquals(0, $order->getTotalPaid());
        $this->assertEquals($order->getGrandTotal(), $order->getTotalDue());
        $this->assertTrue($order->canInvoice());

        // Partially capture the charge
        $paymentIntent = $this->tests->stripe()->paymentIntents->capture($paymentIntent->id, ["amount_to_capture" => 500]);
        $this->assertEquals(500, $paymentIntent->amount_received);
        $this->tests->event()->trigger("charge.captured", $paymentIntent->charges->data[0]);
        $this->tests->event()->trigger("payment_intent.succeeded", $paymentIntent);

        // Refresh the order object
        $order = $this->tests->refreshOrder($order);

        $this->tests->compare($order->getData(), [
            "total_paid" => 0,
            "total_refunded" => "unset",
            "total_canceled" => "unset",
            "total_due" => "53.3000",
            "state" => "processing",
            "status" => "processing"
        ]);

        $transactions = $this->helper->getOrderTransactions($order);
        $captures = $authorizations = $refunds = 0;
        foreach ($transactions as $t)
        {
            switch ($t->getTxnType())
            {
                case "capture":
                    $captures++;
                    break;
                case "authorization":
                    $authorizations++;
                    break;
                case "refund":
                    $refunds++;
                    break;
            }
        }

        $this->assertEquals(1, $captures);
        $this->assertEquals(1, $authorizations);
        $this->assertEquals(0, $refunds);

        $invoicesCollection = $order->getInvoiceCollection();
        $this->assertCount(0, $invoicesCollection);

        // Check the comments
        $histories = $order->getStatusHistories();
        $latestHistoryComment = array_shift($histories);
        $this->assertStringContainsString("Partially captured $5.00 via Stripe, but it less than the order total. Please invoice the order offline with the correct order items.", $latestHistoryComment->getComment());
        $this->assertEquals("processing", $latestHistoryComment->getStatus());
    }
}
