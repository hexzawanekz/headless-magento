<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\Tax\SubscriptionInitialFee;

use StripeIntegration\Tax\Test\Integration\Helper\Calculator;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class PlaceOrderTaxExclusiveTest extends BaseSubscription
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
     */
    public function testSubscriptionCart()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("SubscriptionInitialFee")
            ->setShippingAddress("Romania")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("Romania")
            ->setPaymentMethod("SuccessCard");

        $order = $this->quote->placeOrder();

        $subscriptionDetails = [
            'sku' => 'simple-monthly-subscription-initial-fee-product',
            'qty' => 1,
            'price' => 10,
            'shipping' => 5,
            'initial_fee' => 3,
            'discount' => 0,
            'tax_percent' => $this->calculator->getTaxRate(),
            'mode' => 'exclusive'
        ];
        $this->compareSubscriptionDetails($order, $subscriptionDetails);

        $eventHelper = $this->tests->event();
        $subscriptionId = $order->getPayment()->getAdditionalInformation("subscription_id");
        $eventHelper->triggerSubscriptionEventsById($subscriptionId);

        // Refresh the order object
        $order = $this->tests->refreshOrder($order);

        // Check if Radar risk value is been set to the order
        $this->assertIsNumeric($order->getStripeRadarRiskScore());
        $this->assertGreaterThanOrEqual(0, $order->getStripeRadarRiskScore());
        $this->assertNotEmpty($order->getStripeRadarRiskLevel());

        // Check Stripe Payment method
        $paymentMethod = $this->tests->loadPaymentMethod($order->getId());
        $this->assertEquals('card', $paymentMethod->getPaymentMethodType());

        $invoicesCollection = $order->getInvoiceCollection();

        $this->assertEquals("processing", $order->getState());
        $this->assertEquals("processing", $order->getStatus());
        $this->assertEquals(1, $invoicesCollection->getSize());

        $this->assertFalse($order->canInvoice());

        // Refresh the order object
        $order = $this->tests->refreshOrder($order);

        // Stripe checks
        $orderTotal = 2142; // 10 for item, 5 shipping, 3 initial fee, tax 19% for all: 18 * 1.19 = 21.42
        $subscriptionTotal = 1785; // $10 for the item, $5 for the shipping, $2.85 for tax
        $initialFee = 357; // $3 fee + $0.57 tax
        $initialFeeTax = 0.57;

        $this->assertEquals($initialFeeTax, $order->getInitialFeeTax());

        $paymentIntentId = $order->getPayment()->getLastTransId();
        $paymentIntent = $this->tests->stripe()->paymentIntents->retrieve($paymentIntentId, []);
        $this->tests->compare($paymentIntent, [
            "amount" => $orderTotal,
            "amount_received" => $orderTotal,
            "description" => $this->orderHelper->getOrderDescription($order)
        ]);

        $customerId = $paymentIntent->customer;
        $customer = $this->tests->stripe()->customers->retrieve($customerId);
        $this->assertCount(1, $customer->subscriptions->data);
        $this->tests->compare($customer->subscriptions->data[0], [
            "items" => [
                "data" => [
                    0 => [
                        "plan" => [
                            "amount" => $subscriptionTotal,
                            "currency" => "usd",
                            "interval" => "month",
                            "interval_count" => 1
                        ],
                        "price" => [
                            "recurring" => [
                                "interval" => "month",
                                "interval_count" => 1
                            ],
                            "unit_amount" => $subscriptionTotal
                        ],
                        "quantity" => 1
                    ]
                ]
            ],
            "metadata" => [
                "Order #" => $order->getIncrementId()
            ],
            "status" => "active"
        ]);

        // Check that the upcoming invoice does not include the initial fee
        $invoice = $this->tests->stripe()->invoices->upcoming([ 'customer' => $customerId ]);
        $this->tests->compare($invoice, [
            "amount_due" => $subscriptionTotal,
            "amount_paid" => 0,
            "amount_remaining" => $subscriptionTotal,
            "total" => $subscriptionTotal
        ]);
    }
}