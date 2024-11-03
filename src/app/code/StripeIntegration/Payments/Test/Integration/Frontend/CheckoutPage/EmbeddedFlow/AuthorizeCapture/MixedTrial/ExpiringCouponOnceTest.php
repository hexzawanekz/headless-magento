<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\CheckoutPage\EmbeddedFlow\AuthorizeCapture\MixedTrial;


/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class ExpiringCouponOnceTest extends \PHPUnit\Framework\TestCase
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
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/Discounts.php
     */
    public function testMixedTrial()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("MixedTrial")
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setCouponCode("10_percent_apply_once")
            ->setPaymentMethod("SuccessCard");

        $order = $this->quote->placeOrder();

        $paymentIntent = $this->tests->confirmSubscription($order);

        // Refresh the order object
        $order = $this->tests->refreshOrder($order);

        // Order checks
        $this->tests->compare($order->getData(), [
            "state" => "processing",
            "status" => "processing",
            "grand_total" => 14.74
        ]);

        $customerId = $order->getPayment()->getAdditionalInformation("customer_stripe_id");
        $customer = $this->tests->stripe()->customers->retrieve($customerId, ['expand' => ['subscriptions.data.discounts']]);

        // Customer has one subscription
        $this->assertCount(1, $customer->subscriptions->data);

        // The subscription setup is correct.
        $this->tests->compare($customer->subscriptions->data[0], [
            "items" => [
                "data" => [
                    0 => [
                        "price" => [
                            "recurring" => [
                                "interval" => "month",
                                "interval_count" => 1
                            ],
                        ],
                        "quantity" => 1
                    ]
                ]
            ],
            "metadata" => [
                "Order #" => $order->getIncrementId()
            ],
            "plan" => [
                "interval" => "month",
                "interval_count" => 1,
                "amount" => 1583,
            ],
            "discount" => [
                "coupon" => [
                    "amount_off" => 109,
                    "duration" => "repeating",
                    "duration_in_months" => 1,
                    "name" => "$1.09 Discount",
                ]
            ],
            "status" => "trialing"
        ]);

        // Customer has no charges
        $charges = $this->tests->stripe()->charges->all(['customer' => $customerId]);
        $this->assertCount(1, $charges->data);
        $this->tests->compare($charges->data[0], [
            "amount" => 1474, // Discounted amount
            "amount_refunded" => 0,
            "amount_captured" => 1474,
            "status" => "succeeded",
            "currency" => "usd",
            "metadata" => [
                "Order #" => $order->getIncrementId()
            ]
        ]);

        // Upcoming invoice for subscription
        $upcomingInvoice = $this->tests->stripe()->invoices->upcoming(['customer' => $customer->id]);

        // Upcoming invoice should have a discount
        $this->tests->compare($upcomingInvoice, [
            "amount_due" => 1474,
            "amount_paid" => 0,
            "amount_remaining" => 1474,
            "total" => 1474,
            "discount" => [
                "coupon" => [
                    "amount_off" => 109,
                    "duration" => "repeating",
                    "duration_in_months" => 1,
                    "name" => "$1.09 Discount",
                ]
            ],
        ]);
    }
}
