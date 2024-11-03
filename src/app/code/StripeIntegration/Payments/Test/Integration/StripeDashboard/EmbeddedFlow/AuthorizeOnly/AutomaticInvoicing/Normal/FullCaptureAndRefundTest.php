<?php

namespace StripeIntegration\Payments\Test\Integration\StripeDashboard\EmbeddedFlow\AuthorizeOnly\AutomaticInvoicing\Normal;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class FullCaptureAndRefundTest extends \PHPUnit\Framework\TestCase
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
    public function testFullCapture()
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
        $invoice = $invoicesCollection->getFirstItem();
        $this->assertTrue($invoice->canCapture());
        $this->assertEquals(\Magento\Sales\Model\Order\Invoice::STATE_OPEN, $invoice->getState());

        // Capture the invoice via Stripe
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

        // Full capture
        $paymentIntent = $this->tests->stripe()->paymentIntents->capture($paymentIntent->id);
        $this->assertEquals(5330, $paymentIntent->charges->data[0]->amount_captured);
        $this->tests->event()->trigger("charge.captured", $paymentIntent->charges->data[0]);
        $this->tests->event()->trigger("payment_intent.succeeded", $paymentIntent);

        // Refresh the order object
        $order = $this->tests->refreshOrder($order);

        $this->tests->compare($order->getData(), [
            "total_paid" => 53.30,
            "total_refunded" => "unset",
            "total_canceled" => "unset",
            "total_due" => "0.0000",
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
        $this->assertEquals(\Magento\Sales\Model\Order\Invoice::STATE_PAID, $invoice->getState());

        // Create an online credit memo from Magento
        $this->tests->refundOnline($invoice, ["simple-product" => 2, "virtual-product" => 2], $shippingAmount = 10);

        // Refresh the order object
        $this->helper->clearCache();
        $order = $this->tests->orderHelper->loadOrderByIncrementId($order->getIncrementId());
        $this->tests->compare($order->getData(), [
            "grand_total" => "53.3000",
            "total_refunded" => "53.3000",
            "state" => "closed",
            "status" => "closed"
        ]);
    }
}
