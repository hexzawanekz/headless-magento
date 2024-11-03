<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\CheckoutPage\EmbeddedFlow\AuthorizeCapture\SubscriptionStartDates;

use StripeIntegration\Payments\Test\Integration\Mock\StripeIntegration\Payments\Model\Config as ConfigMock;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class OrderDate3DSTest extends \PHPUnit\Framework\TestCase
{
    private $compare;
    private $objectManager;
    private $quote;
    private $tests;
    private $subscriptionOptionsCollectionFactory;
    private $config;

    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();

        $this->objectManager->configure([
            'preferences' => [
                \StripeIntegration\Payments\Model\Config::class => ConfigMock::class,
            ]
        ]);

        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests($this);
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();
        $this->compare = new \StripeIntegration\Payments\Test\Integration\Helper\Compare($this);
        $this->subscriptionOptionsCollectionFactory = $this->objectManager->create(\StripeIntegration\Payments\Model\ResourceModel\SubscriptionOptions\CollectionFactory::class);
        $this->config = $this->objectManager->get(\StripeIntegration\Payments\Model\Config::class);
    }

    /**
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 0
     */
    public function testPlaceOrder()
    {
        $day = "10";
        if (date("d") == $day)
        {
            // 3DS is used and an attempt is made to start the subscription today. Incomplete because it requires authentication.
            $expectedSubscriptionStatus = "incomplete";
        }
        else
        {
            // The subscription is set up to start in the future, no payment is needed now and therefore no authentication is performed.
            // This case needs some more investigation, past_due means that the payment could not be collected, likely because authentication is needed.
            $expectedSubscriptionStatus = "past_due";
        }

        $this->config->manualAuthenticationPaymentMethods = [];

        $product = $this->tests->getProduct('simple-monthly-subscription-product');
        $product->setSubscriptionOptions([
            'start_on_specific_date' => 1,
            'start_date' => "2021-01-$day",
            'first_payment' => 'on_order_date',
            'prorate_first_payment' => 0
        ]);
        $this->tests->helper()->saveProduct($product);

        $subscriptionOptionsCollection = $this->subscriptionOptionsCollectionFactory->create();
        $subscriptionOptionsCollection->addFieldToFilter('product_id', $product->getId());
        $this->assertCount(1, $subscriptionOptionsCollection->getItems());

        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("Subscription")
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setPaymentMethod("AuthenticationRequiredCard");

        $order = $this->quote->placeOrder();
        $subscription = $this->tests->confirmSubscription($order);

        // Refresh the order object
        $order = $this->tests->refreshOrder($order);

        $customerId = $subscription->customer;
        $customer = $this->tests->stripe()->customers->retrieve($customerId);

        // Customer has one subscription
        $this->assertCount(1, $customer->subscriptions->data);

        // The customer has no charge because no 3DS was performed client side
        $charges = $this->tests->stripe()->charges->all(['customer' => $customerId]);
        $this->assertCount(0, $charges->data);

        $subscription = $customer->subscriptions->data[0];
        // Get the subscription start date
        $subscriptionStartDate = $subscription->billing_cycle_anchor;

        // The subscription start date should be today
        $this->assertEquals(date("d", time()), date("d", $subscriptionStartDate));

        $this->compare->object($customer->subscriptions->data[0], [
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
            "status" => $expectedSubscriptionStatus,
            "description" => "Subscription order #{$order->getIncrementId()} by Joyce Strother",
            "discount" => null
        ]);

        // The upcoming invoice should be on the 10th
        $upcomingInvoice = $this->tests->stripe()->invoices->upcoming([
            'customer' => $customerId,
            'subscription' => $subscription->id
        ]);
        $this->assertEquals($day, date("d", $upcomingInvoice->next_payment_attempt));
    }
}
