<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\CheckoutPage\EmbeddedFlow\AuthorizeCapture\MixedCart;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class StartDateTest extends \PHPUnit\Framework\TestCase
{
    private $compare;
    private $objectManager;
    private $quote;
    private $tests;
    private $subscriptionOptionsCollectionFactory;

    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests($this);
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();
        $this->compare = new \StripeIntegration\Payments\Test\Integration\Helper\Compare($this);
        $this->subscriptionOptionsCollectionFactory = $this->objectManager->create(\StripeIntegration\Payments\Model\ResourceModel\SubscriptionOptions\CollectionFactory::class);
    }

    /**
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 0
     */
    public function testPlaceOrder()
    {
        $day = "10";
        $product = $this->tests->getProduct('simple-monthly-subscription-initial-fee-product');
        $product->setSubscriptionOptions([
            'start_on_specific_date' => 1,
            'start_date' => "2021-01-$day",
            'first_payment' => 'on_start_date',
            'prorate_first_payment' => 0
        ]);
        $this->tests->helper()->saveProduct($product);

        $subscriptionOptionsCollection = $this->subscriptionOptionsCollectionFactory->create();
        $subscriptionOptionsCollection->addFieldToFilter('product_id', $product->getId());
        $this->assertCount(1, $subscriptionOptionsCollection->getItems());

        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("MixedCart")
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setPaymentMethod("SuccessCard");

        $order = $this->quote->placeOrder();
        $subscription = $this->tests->confirmSubscription($order);

        // Refresh the order object
        $order = $this->tests->refreshOrder($order);

        $customerId = $subscription->customer;
        $customer = $this->tests->stripe()->customers->retrieve($customerId);

        // Customer has one subscription
        $this->assertCount(1, $customer->subscriptions->data);

        $productsAmount = 3815; // 2 x Simple product + 2 x Initial fee + tax
        $subscriptionAmount = 3165; // 2 x Subscription product + tax
        if (date("d") == $day)
        {
            $expectedChargeAmount = $productsAmount + $subscriptionAmount;
            $expectedSubscriptionStatus = "active";
        }
        else
        {
            $expectedChargeAmount = $productsAmount;
            $expectedSubscriptionStatus = "trialing";
        }

        // Check the charge amount
        $charges = $this->tests->stripe()->charges->all(['customer' => $customerId]);
        $this->assertCount(1, $charges->data);
        $this->assertEquals($expectedChargeAmount, $charges->data[0]->amount);

        $subscription = $customer->subscriptions->data[0];
        // Get the subscription start date
        $subscriptionStartDate = $subscription->billing_cycle_anchor;

        // The subscription start date should be the 10th of the month
        $this->assertEquals($day, date("d", $subscriptionStartDate));

        $this->compare->object($subscription, [
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
                "Order #" => $order->getIncrementId(),
                "SubscriptionProductIDs" => $product->getId(),
                "Type" => "SubscriptionsTotal"
            ],
            "status" => $expectedSubscriptionStatus,
            "discount" => null
        ]);

        // The order should be partially refunded
        $order = $this->tests->refreshOrder($order);
        $this->tests->compare($order->getData(),[
            'grand_total' => round($expectedChargeAmount / 100, 2),
            'total_paid' => round($expectedChargeAmount / 100, 2),
            'total_refunded' => 0
        ]);
    }
}
