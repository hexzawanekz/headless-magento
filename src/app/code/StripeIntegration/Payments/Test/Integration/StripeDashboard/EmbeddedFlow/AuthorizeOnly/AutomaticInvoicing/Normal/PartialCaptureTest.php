<?php

namespace StripeIntegration\Payments\Test\Integration\StripeDashboard\EmbeddedFlow\AuthorizeOnly\AutomaticInvoicing\Normal;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class PartialCaptureTest extends \PHPUnit\Framework\TestCase
{
    private $compare;
    private $helper;
    private $objectManager;
    private $quote;
    private $tests;

    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests($this);
        $this->compare = new \StripeIntegration\Payments\Test\Integration\Helper\Compare($this);
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();

        $this->helper = $this->objectManager->get(\StripeIntegration\Payments\Helper\Generic::class);
    }

    /**
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 0
     * @magentoConfigFixture current_store payment/stripe_payments/payment_action authorize
     * @magentoConfigFixture current_store payment/stripe_payments/automatic_invoicing 1
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

        // Order checks
        $this->tests->compare($order->getData(), [
            "total_paid" => 0,
            "total_refunded" => "unset",
            "total_canceled" => "unset",
            "total_due" => $order->getGrandTotal(),
            "state" => "processing",
            "status" => "processing"
        ]);

        $invoicesCollection = $order->getInvoiceCollection();
        $invoice = $invoicesCollection->getFirstItem();
        $this->assertTrue($invoice->canCapture());
        $this->assertEquals(\Magento\Sales\Model\Order\Invoice::STATE_OPEN, $invoice->getState());

        $paymentIntent = $this->tests->stripe()->paymentIntents->retrieve($paymentIntent->id);
        $this->compare->object($paymentIntent, [
            "amount_capturable" => 5330,
            "payment_method_options" => [
                "card" => [
                    "capture_method" => "manual"
                ]
            ],
            "status" => "requires_capture"
        ]);

        // Partially capture the payment
        $paymentIntent = $this->tests->stripe()->paymentIntents->capture($paymentIntent->id, ["amount_to_capture" => 1000]);
        $this->assertEquals(1000, $paymentIntent->charges->data[0]->amount_captured);
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

        // Check the invoice
        $invoice = $order->getInvoiceCollection()->getFirstItem();
        $this->assertEquals(\Magento\Sales\Model\Order\Invoice::STATE_OPEN, $invoice->getState());

        // Check the comments
        $histories = $order->getStatusHistories();
        $latestHistoryComment = array_shift($histories);
        $this->assertStringContainsString("Partially captured $10.00 via Stripe, but it less than the order total. Please invoice the order offline with the correct order items.", $latestHistoryComment->getComment());
        $this->assertEquals("processing", $latestHistoryComment->getStatus());
    }
}
