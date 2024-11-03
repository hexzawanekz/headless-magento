<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\CheckoutPage\EmbeddedFlow\AuthorizeCapture\TrialSimple;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class TaxInclusivePricesTest extends \PHPUnit\Framework\TestCase
{
    private $objectManager;
    private $quote;
    private $subscriptions;
    private $subscriptionProductFactory;
    private $checkoutFlow;

    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->subscriptions = $this->objectManager->get(\StripeIntegration\Payments\Helper\Subscriptions::class);
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();
        $this->subscriptionProductFactory = $this->objectManager->get(\StripeIntegration\Payments\Model\SubscriptionProductFactory::class);
        $this->checkoutFlow = $this->objectManager->get(\StripeIntegration\Payments\Model\Checkout\Flow::class);
    }

    /**
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 0
     *
     * @magentoConfigFixture current_store customer/create_account/default_group 1
     * @magentoConfigFixture current_store customer/create_account/auto_group_assign 1
     * @magentoConfigFixture current_store tax/classes/shipping_tax_class 2
     * @magentoConfigFixture current_store tax/calculation/price_includes_tax 1
     * @magentoConfigFixture current_store tax/calculation/shipping_includes_tax 1
     * @magentoConfigFixture current_store tax/calculation/discount_tax 1
     */
    public function testTrialCart()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("TrialSimple")
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setPaymentMethod("SuccessCard");

        $quote = $this->quote->getQuote();

        $profile = [];

        foreach ($quote->getAllItems() as $quoteItem)
        {
            $subscriptionProductModel = $this->subscriptionProductFactory->create()->fromQuoteItem($quoteItem);
            $profile = $this->subscriptions->getSubscriptionDetails($subscriptionProductModel, $quote, $quoteItem);
            $this->assertEquals("Simple Trial Monthly Subscription", $profile["name"]);
            $this->assertEquals(1, $profile["qty"]);
            $this->assertEquals("month", $profile["interval"]);
            $this->assertEquals(1, $profile["interval_count"]);
            $this->assertEquals(10, $profile["amount_magento"]);
            $this->assertEquals(1000, $profile["amount_stripe"]);
            $this->assertEquals(0, $profile["initial_fee_stripe"]);
            $this->assertEquals(0, $profile["initial_fee_magento"]);
            $this->assertEquals(0, $profile["discount_amount_magento"]);
            $this->assertEquals(0, $profile["discount_amount_stripe"]);
            $this->assertEquals(5, $profile["shipping_magento"]);
            $this->assertEquals(500, $profile["shipping_stripe"]);
            $this->assertEquals(8.25, $profile["tax_percent"]);
            $this->assertEquals(0.76, $profile["tax_amount_item"]);
            $this->assertEquals(0.38, $profile["tax_amount_shipping"]);
            $this->assertEquals(0, $profile["tax_amount_initial_fee"]);
            $this->assertEmpty($profile["trial_end"]);
            $this->assertEquals(14, $profile["trial_days"]);
            $this->assertEmpty($profile["expiring_coupon"]);
        }

        $order = $this->quote->placeOrder();

        $this->checkoutFlow->isNewOrderBeingPlaced = true;
        foreach ($order->getAllItems() as $orderItem)
        {
            $subscriptionProductModel = $this->subscriptionProductFactory->create()->fromOrderItem($orderItem);
            $profile = $this->subscriptions->getSubscriptionDetails($subscriptionProductModel, $order, $orderItem);
            $this->assertEquals("Simple Trial Monthly Subscription", $profile["name"]);
            $this->assertEquals(1, $profile["qty"]);
            $this->assertEquals("month", $profile["interval"]);
            $this->assertEquals(1, $profile["interval_count"]);
            $this->assertEquals(10, $profile["amount_magento"]);
            $this->assertEquals(1000, $profile["amount_stripe"]);
            $this->assertEquals(0, $profile["initial_fee_stripe"]);
            $this->assertEquals(0, $profile["initial_fee_magento"]);
            $this->assertEquals(0, $profile["discount_amount_magento"]);
            $this->assertEquals(0, $profile["discount_amount_stripe"]);
            $this->assertEquals(5, $profile["shipping_magento"]);
            $this->assertEquals(500, $profile["shipping_stripe"]);
            $this->assertEquals(8.25, $profile["tax_percent"]);
            $this->assertEquals(0.76, $profile["tax_amount_item"]);
            $this->assertEquals(0.38, $profile["tax_amount_shipping"]);
            $this->assertEquals(0, $profile["tax_amount_initial_fee"]);
            $this->assertEmpty($profile["trial_end"]);
            $this->assertEquals(14, $profile["trial_days"]);
            $this->assertEmpty($profile["expiring_coupon"]);
        }

        $uiConfigProvider = $this->objectManager->get(\StripeIntegration\Payments\Model\Ui\ConfigProvider::class);
        $uiConfig = $uiConfigProvider->getConfig();
        $this->assertNotEmpty($uiConfig["payment"]["stripe_payments"]["trialingSubscriptions"]);
        $trialSubscriptionsConfig = $uiConfig["payment"]["stripe_payments"]["trialingSubscriptions"];

        $this->assertEquals(10, $trialSubscriptionsConfig["subscriptions_total"], "Subtotal");
        $this->assertEquals(10, $trialSubscriptionsConfig["base_subscriptions_total"], "Base Subtotal");

        $this->assertEquals(5, $trialSubscriptionsConfig["shipping_total"], "Shipping");
        $this->assertEquals(5, $trialSubscriptionsConfig["base_shipping_total"], "Base Shipping");

        $this->assertEquals(0, $trialSubscriptionsConfig["discount_total"], "Discount");
        $this->assertEquals(0, $trialSubscriptionsConfig["base_discount_total"], "Base Discount");

        $this->assertEquals(1.14, $trialSubscriptionsConfig["tax_total"], "Tax");
        $this->assertEquals(1.14, $trialSubscriptionsConfig["tax_total"], "Base Tax");
    }
}
