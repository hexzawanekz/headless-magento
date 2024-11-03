<?php

namespace StripeIntegration\Payments\Test\Integration\Unit\Helper;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class SubscriptionsTest extends \PHPUnit\Framework\TestCase
{
    private $objectManager;
    private $quote;
    private $tests;
    private $subscriptionProductFactory;

    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();
        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests($this);
        $this->subscriptionProductFactory = $this->objectManager->get(\StripeIntegration\Payments\Model\SubscriptionProductFactory::class);
    }

    /**
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 0
     */
    public function testGetSubscriptionDetails()
    {
        $subscriptionsHelper = $this->objectManager->get(\StripeIntegration\Payments\Helper\Subscriptions::class);

        $this->quote->create()
            ->setCustomer('Guest')
            ->addProduct('configurable-subscription', 10, [["subscription" => "monthly"]])
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setPaymentMethod("SuccessCard");

        $quote = $this->quote->getQuote();

        foreach ($quote->getAllItems() as $quoteItem)
        {
            if ($quoteItem->getProductType() == "configurable")
                continue;

            $this->assertNotEmpty($quoteItem->getProduct()->getId());
            $subscriptionProductModel = $this->subscriptionProductFactory->create()->fromQuoteItem($quoteItem);
            $profile = $subscriptionsHelper->getSubscriptionDetails($subscriptionProductModel, $quote, $quoteItem);

            $this->tests->compare($profile, [
                "name" => "Configurable Subscription",
                "qty" => 10,
                "interval" => "month",
                "amount_magento" => 10,
                "amount_stripe" => 1000,
                "shipping_magento" => 50,
                "shipping_stripe" => 5000,
                "currency" => "usd",
                "tax_percent" => 8.25,
                "tax_percent_shipping" => 0,
                "tax_amount_item" => 8.25,
                "tax_amount_item_stripe" => 825
            ]);
        }
    }
}
