<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\Tax\SubscriptionInitialFee;

use StripeIntegration\Tax\Test\Integration\Helper\Calculator;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class DiscountTaxExclusiveTest extends BaseSubscription
{
    private $quote;
    private $tests;
    private $orderHelper;
    private $calculator;

    public function setUp(): void
    {
        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests($this);
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();
        $this->orderHelper = $this->tests->objectManager->get(\StripeIntegration\Payments\Helper\Order::class);
        $this->calculator = new Calculator('Romania');
    }

    /**
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Tax/Test/Integration/_files/Data/Enable.php
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Tax/TaxApiKeys.php
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Tax/TaxClasses.php
     * @magentoConfigFixture current_store tax/stripe_tax/prices_and_promotions_tax_behavior exclusive
     * @magentoConfigFixture current_store tax/stripe_tax/shipping_tax_behavior exclusive
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 0
     * @magentoConfigFixture current_store payment/stripe_payments/save_payment_method 0
     * @magentoConfigFixture current_store payment/stripe_payments/payment_action authorize_capture
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/Discounts.php
     */
    public function testSubscriptionCart()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("SubscriptionInitialFee")
            ->setShippingAddress("Romania")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("Romania")
            ->setCouponCode("10_percent")
            ->setPaymentMethod("SuccessCard");

        $order = $this->quote->placeOrder();
        $ordersCount = $this->tests->getOrdersCount();
        $paymentIntent = $this->tests->confirmSubscription($order);

        // Refresh the order object
        $order = $this->tests->refreshOrder($order);
        $orderIncrementId = $order->getIncrementId();

        $subscriptionDetails = [
            'sku' => 'simple-monthly-subscription-initial-fee-product',
            'qty' => 1,
            'price' => 10,
            'shipping' => 5,
            'initial_fee' => 3,
            'discount' => 1,
            'tax_percent' => $this->calculator->getTaxRate(),
            'mode' => 'exclusive'
        ];
        $this->compareSubscriptionDetails($order, $subscriptionDetails);

        $orderTotal = 2023; // 10 for item, 1 discount, 5 shipping, 3 initial fee, tax 19% for all: 17 * 1.19 = 20.23
        $subscriptionTotal = 1666; // $10 for the item, 1 discount, $5 for the shipping, $2.66 for tax
        $initialFee = 357; // $3 fee + $0.57 tax
        $initialFeeTax = 0.57;

        $this->tests->compare($order->debug(), [
            "state" => "processing",
            "status" => "processing",
            "base_subtotal" => 10,
            "base_discount_amount" => -1,
            "base_total_paid" => $orderTotal / 100,
            "total_paid" => $orderTotal / 100,
        ]);

        $this->tests->compare($paymentIntent, [
            "amount" => $orderTotal,
            "description" => $this->orderHelper->getOrderDescription($order)
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