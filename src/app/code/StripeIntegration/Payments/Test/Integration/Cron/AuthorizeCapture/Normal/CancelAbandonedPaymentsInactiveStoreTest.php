<?php

namespace StripeIntegration\Payments\Test\Integration\Cron\AuthorizeCapture\Normal;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class CancelAbandonedPaymentsInactiveStoreTest extends \PHPUnit\Framework\TestCase
{
    private $objectManager;
    private $tests;
    private $quote;
    private $storeManager;

    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests($this);
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();
        $this->storeManager =  $this->objectManager->get(\Magento\Store\Model\StoreManagerInterface::class);
    }

    /**
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/ApiKeysIsolated.php
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 0
     * @magentoConfigFixture current_store currency/options/base USD
     * @magentoConfigFixture current_store currency/options/allow EUR,USD
     * @magentoConfigFixture current_store currency/options/default EUR
     */
    public function testCleanup()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("Normal")
            ->setShippingAddress("Berlin")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("Berlin")
            ->setPaymentMethod("SOFORT");

        $order = $this->quote->placeOrder();

        $cron = $this->objectManager->create(\StripeIntegration\Payments\Cron\WebhooksPing::class);

        // Inactivate the store
        $secondStore = $this->storeManager->getStore("second_store");
        $secondStore->setIsActive(0);
        $secondStore->save();

        // Test webhook pings
        $cron->pingWebhookEndpoints();

        // Test canceling abandoned orders
        $canceledPaymentIntents = $cron->cancelAbandonedPayments(0, 1);
        foreach ($canceledPaymentIntents as $paymentIntent)
        {
            if ($paymentIntent->metadata->{"Order #"} == $order->getIncrementId())
            {
                $this->tests->event()->trigger("payment_intent.canceled", $paymentIntent);
            }
        }

        $order = $this->tests->refreshOrder($order);
        $this->assertEquals("canceled", $order->getState());
        $this->assertEquals("canceled", $order->getStatus());
    }
}
