<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\CheckoutPage\EmbeddedFlow\AuthorizeCapture\SubscriptionInitialFee;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class DiscountTest extends \PHPUnit\Framework\TestCase
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
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/Discounts.php
     */
    public function testSubscriptionCart()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("SubscriptionInitialFee")
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setCouponCode("10_percent")
            ->setPaymentMethod("SuccessCard");

        $order = $this->quote->placeOrder();
        $ordersCount = $this->tests->getOrdersCount();
        $paymentIntent = $this->tests->confirmSubscription($order);

        // Refresh the order object
        $order = $this->tests->refreshOrder($order);
        $orderIncrementId = $order->getIncrementId();

        $orderTotal = 1799; // 10 Price, 1 discount, 5 shipping (not taxed), 3 initial fee: 12 * 1.0825 + 5 = 17.99
        $initialFee = 325;
        $initialFeeTax = 0.25;

        $this->tests->compare($order->debug(), [
            "state" => "processing",
            "status" => "processing",
            "base_subtotal" => 10,
            "base_discount_amount" => -1,
            "base_total_paid" => $orderTotal / 100,
            "total_paid" => $orderTotal / 100,
        ]);

        // $paymentIntent = $this->tests->stripe()->paymentIntents->retrieve($paymentIntent->id);

        $this->tests->compare($paymentIntent, [
            "amount" => $orderTotal,
            "description" => "Subscription order #$orderIncrementId by Joyce Strother"
        ]);

        // Trigger webhook events for recurring order
        $this->tests->event()->trigger("charge.succeeded", $paymentIntent->charges->data[0]->id);
        $this->tests->event()->trigger("invoice.payment_succeeded", $paymentIntent->invoice, ['billing_reason' => 'subscription_cycle']);

        // Make sure a new order was created
        $newOrdersCount = $this->tests->getOrdersCount();
        $this->assertEquals($ordersCount + 1, $newOrdersCount);

        // Get the recurring order
        $recurringOrder = $this->tests->getLastOrder();

        // Order checks
        $this->tests->compare($recurringOrder->getData(), [
            "discount_amount" => $order->getDiscountAmount(),
            'grand_total' => round(floatval($order->getGrandTotal()) - ($initialFee / 100), 2), // 3.25 is initial fee + tax
            'shipping_amount' => $order->getShippingAmount(),
            'tax_amount' => round(floatval($order->getTaxAmount()) - $initialFeeTax, 2), // 0.25 is the tax for the initial fee
        ]);
    }
}
