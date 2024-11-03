<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\CheckoutPage\EmbeddedFlow\AuthorizeCapture\Subscription;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class PlaceOrderTest extends \PHPUnit\Framework\TestCase
{
    private $objectManager;
    private $quote;
    private $tests;

    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests($this);
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();
    }

    /**
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 0
     * @magentoConfigFixture current_store payment/stripe_payments/origin_check 0
     */
    public function testPlaceOrder()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("SubscriptionSingle")
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setPaymentMethod("SuccessCard");

        $order = $this->quote->placeOrder();
        $this->tests->confirmSubscription($order);

        // Create the payment info block for $order
        $paymentInfoBlock = $this->objectManager->create(\StripeIntegration\Payments\Block\PaymentInfo\Element::class);
        $paymentInfoBlock->setOrder($order);
        $paymentInfoBlock->setInfo($order->getPayment());

        // Test the payment info block
        $paymentMethod = $paymentInfoBlock->getPaymentMethod();
        $formattedAmount = $paymentInfoBlock->getFormattedAmount();
        $paymentStatus = $paymentInfoBlock->getPaymentStatus();
        $isStripeMethod = $paymentInfoBlock->isStripeMethod();
        $paymentIntent = $paymentInfoBlock->getPaymentIntent();
        $subscription = $paymentInfoBlock->getSubscription();
        $setupIntent = $paymentInfoBlock->getSetupIntent();
        $subscriptionOrderUrl = $paymentInfoBlock->getSubscriptionOrderUrl($order->getIncrementId());
        $formattedSubscriptionAmount = (string)$paymentInfoBlock->getFormattedSubscriptionAmount();
        $customerId = $paymentInfoBlock->getCustomerId();

        $this->assertNotEmpty($paymentIntent);
        $this->assertNotEmpty($paymentMethod);
        $this->assertNotEmpty($subscription);

        $this->assertStringStartsWith("pm_", $paymentMethod->id);
        $this->assertEquals("$15.83", $formattedAmount);
        $this->assertEquals("succeeded", $paymentStatus);
        $this->assertTrue($isStripeMethod);
        $this->assertStringStartsWith("pi_", $paymentIntent->id);
        $this->assertStringStartsWith("sub_", $subscription->id);
        $this->assertEmpty($setupIntent);
        $this->assertStringStartsWith("http", $subscriptionOrderUrl);
        $this->assertStringEndsWith($order->getId() . "/", $subscriptionOrderUrl);
        $this->assertEquals("$15.83 every month", $formattedSubscriptionAmount);
        $this->assertStringStartsWith("cus_", $customerId);
    }
}
