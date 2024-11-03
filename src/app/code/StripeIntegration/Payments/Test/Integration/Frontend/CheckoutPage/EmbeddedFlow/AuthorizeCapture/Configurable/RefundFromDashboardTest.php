<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\CheckoutPage\EmbeddedFlow\AuthorizeCapture\Configurable;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class RefundFromDashboardTest extends \PHPUnit\Framework\TestCase
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
     * @magentoConfigFixture current_store payment/stripe_payments/payment_action authorize_capture
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/Discounts.php
     */
    public function testNormalCart()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("Configurable")
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setCouponCode("10_percent")
            ->setPaymentMethod("SuccessCard");

        $order = $this->quote->placeOrder();
        $paymentIntent = $this->tests->confirm($order);

        // Refresh the order object
        $order = $this->tests->refreshOrder($order);

        $this->tests->compare($order->getData(), [
            "grand_total" => "14.7400",
            "discount_amount" => "-1.0000",
            "coupon_code" => "10_percent",
            "total_due" => "0.0000",
            "total_paid" => "14.7400",
        ]);

        // Partially refund the charge
        $refund = $this->tests->stripe()->refunds->create(['charge' => $paymentIntent->charges->data[0], 'amount' => 7]);

        // charge.refunded
        $this->tests->event()->trigger("charge.refunded", $paymentIntent->charges->data[0]->id);

        // Refresh the order object
        $order = $this->tests->refreshOrder($order);

        $this->tests->compare($order->getData(), [
            "total_refunded" => "unset",
        ]);

        $histories = $order->getStatusHistories();
        $latestHistoryComment = array_shift($histories);
        $comment = $latestHistoryComment->getComment();
        $status = $latestHistoryComment->getStatus();
        $this->assertEquals("A refund of $0.07 was issued via Stripe, but the amount is different than the order amount.", $comment);
        $this->assertEquals("processing", $status);
    }
}
