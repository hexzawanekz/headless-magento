<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\RedirectFlow\AuthorizeCapture\Trial;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class PlaceOrderTest extends \PHPUnit\Framework\TestCase
{
    private $quote;
    private $tests;

    public function setUp(): void
    {
        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests($this);
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();
    }

    /**
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 1
     *
     * @magentoConfigFixture current_store general/country/allow US
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/ApiKeysLegacy.php
     */
    public function testPlaceOrder()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("TrialSimple")
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setPaymentMethod("StripeCheckout");

        // Place the order
        $order = $this->quote->placeOrder();
        $orderIncrementId = $order->getIncrementId();

        // Confirm the payment
        $method = "card";
        $session = $this->tests->checkout()->retrieveSession($order, "Trial");
        $response = $this->tests->checkout()->confirm($session, $order, $method, "California");

        // Wait until the subscription is creared and retrieve it
        $customerId = $response->customer->id;
        $wait = 8;
        while ($wait > 0)
        {
            sleep(2);
            $wait--;

            $subscriptions = $this->tests->stripe()->subscriptions->all(['limit' => 3, 'customer' => $customerId]);
            if (count($subscriptions->data) > 0)
                break;
        }

        $this->assertCount(1, $subscriptions->data);
        $this->tests->compare($subscriptions->data[0], [
            "status" => "trialing",
            "plan" => [
                "amount" => 1583
            ],
            "metadata" => [
                "Order #" => $orderIncrementId
            ]
        ]);
        $this->assertNotEmpty($subscriptions->data[0]->metadata->{"SubscriptionProductIDs"});

        $ordersCount = $this->tests->getOrdersCount();

        // Trigger charge.succeeded & payment_intent.succeeded & invoice.payment_succeeded
        $subscription = $subscriptions->data[0];
        $this->tests->event()->triggerSubscriptionEvents($subscription, $this);

        // Refresh the order
        $order = $this->tests->refreshOrder($order);

        // There should be no charge for trial subscriptions
        $this->assertIsNotNumeric($order->getStripeRadarRiskScore());
        $this->assertEquals('NA', $order->getStripeRadarRiskLevel());

        // Check Stripe Payment method
        $paymentMethod = $this->tests->loadPaymentMethod($order->getId());
        $this->assertEquals('', $paymentMethod->getPaymentMethodType());

        $this->tests->compare($order->getData(), [
            "grand_total" => 0,
            "total_paid" => 0,
            "total_refunded" => "unset",
            "state" => "processing",
            "status" => "processing"
        ]);

        // End the trial
        $this->tests->endTrialSubscription($subscription->id);

        // Ensure that a new order was created
        $newOrdersCount = $this->tests->getOrdersCount();
        $this->assertEquals($ordersCount + 1, $newOrdersCount);
        $newOrder = $this->tests->getLastOrder();
        $this->assertNotEquals($order->getId(), $newOrder->getId());

        $this->tests->compare($newOrder->getData(), [
            "grand_total" => 15.83,
            "total_paid" => 15.83,
            "total_refunded" => "unset",
            "state" => "processing",
            "status" => "processing"
        ]);
    }
}
