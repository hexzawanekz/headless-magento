<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\CheckoutPage\EmbeddedFlow\AuthorizeCapture\TrialSimple;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class CheckoutTotalsExpiringCouponTest extends \PHPUnit\Framework\TestCase
{
    private $objectManager;
    private $quote;
    private $service;

    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();
        $this->service = $this->objectManager->get(\StripeIntegration\Payments\Api\Service::class);
    }

    /**
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 0
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/Discounts.php
     */
    public function testTrialCartCheckoutTotals()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("TrialSimple")
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setCouponCode("10_percent_apply_once")
            ->setPaymentMethod("SuccessCard");

        $quote = $this->quote->getQuote();
        $this->assertEquals("10_percent_apply_once", $quote->getCouponCode());

        $trialSubscriptionsConfig = $this->service->get_trialing_subscriptions(
            $quote->getBillingAddress()->getData(),
            $quote->getShippingAddress()->getData(),
            $quote->getShippingAddress()->getShippingMethod(),
            $quote->getCouponCode()
        );
        $trialSubscriptionsConfig = json_decode($trialSubscriptionsConfig, true);

        $order = $this->quote->placeOrder();
        $this->assertEquals(10, $trialSubscriptionsConfig["subscriptions_total"], "Subtotal");
        $this->assertEquals(10, $trialSubscriptionsConfig["base_subscriptions_total"], "Base Subtotal");

        $this->assertEquals(5, $trialSubscriptionsConfig["shipping_total"], "Shipping");
        $this->assertEquals(5, $trialSubscriptionsConfig["base_shipping_total"], "Base Shipping");

        $this->assertEquals(1, $trialSubscriptionsConfig["discount_total"], "Discount");
        $this->assertEquals(1, $trialSubscriptionsConfig["base_discount_total"], "Base Discount");

        $this->assertEquals(0.74, $trialSubscriptionsConfig["tax_total"], "Tax");
        $this->assertEquals(0.74, $trialSubscriptionsConfig["tax_total"], "Base Tax");
    }
}
