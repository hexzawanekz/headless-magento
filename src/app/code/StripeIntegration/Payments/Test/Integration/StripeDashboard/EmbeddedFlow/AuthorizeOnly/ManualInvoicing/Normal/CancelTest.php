<?php

namespace StripeIntegration\Payments\Test\Integration\StripeDashboard\EmbeddedFlow\AuthorizeOnly\ManualInvoicing\Normal;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class CancelTest extends \PHPUnit\Framework\TestCase
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
     */
    public function testCancel()
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
        $this->assertEquals(0, $order->getTotalPaid());
        $this->assertEquals(0, $order->getTotalRefunded());
        $this->assertEquals($order->getGrandTotal(), $order->getTotalDue());

        $invoicesCollection = $order->getInvoiceCollection();
        $this->assertCount(0, $invoicesCollection);

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

        // Cancel the payment intent
        $paymentIntent = $this->tests->stripe()->paymentIntents->cancel($paymentIntent->id);
        $this->tests->event()->trigger("payment_intent.canceled", $paymentIntent);
        $this->tests->event()->trigger("charge.refunded", $paymentIntent->charges->data[0]);

        // // Refresh the order object
        $order = $this->tests->refreshOrder($order);

        $this->tests->compare($order->getData(), [
            "total_paid" => 0,
            "total_due" => "53.3000",
            "total_refunded" => "unset",
            "total_canceled" => $order->getGrandTotal(),
            "state" => "canceled",
            "status" => "canceled"
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

        $this->assertEquals(0, $captures);
        $this->assertEquals(1, $authorizations);
        $this->assertEquals(1, $refunds);

        // Check there is no invoice
        $this->assertEquals(0, $order->getInvoiceCollection()->count());

        // Check the comments
        $histories = $order->getStatusHistories();
        $latestHistoryComment = array_shift($histories);
        $this->assertEquals("The payment of $53.30 was canceled via Stripe.", $latestHistoryComment->getComment());
        $this->assertEquals("canceled", $latestHistoryComment->getStatus());
    }
}
