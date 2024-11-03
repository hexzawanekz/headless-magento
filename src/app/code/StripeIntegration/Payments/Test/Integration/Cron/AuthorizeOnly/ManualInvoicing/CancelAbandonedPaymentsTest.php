<?php

namespace StripeIntegration\Payments\Test\Integration\Cron\AuthorizeOnly\ManualInvoicing;

use StripeIntegration\Payments\Test\Integration\Mock\StripeIntegration\Payments\Model\Config as ConfigMock;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class CancelAbandonedPaymentsTest extends \PHPUnit\Framework\TestCase
{
    private $objectManager;
    private $tests;
    private $quote;
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
        $this->config = $this->objectManager->get(\StripeIntegration\Payments\Model\Config::class);
    }

    /**
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/ApiKeysIsolated.php
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 0
     * @magentoConfigFixture current_store payment/stripe_payments/payment_action authorize
     */
    public function testCron()
    {
        $this->config->manualAuthenticationPaymentMethods = [];

        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("Normal")
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setPaymentMethod("AuthenticationRequiredCard");

        $order = $this->quote->placeOrder();

        $cron = $this->objectManager->create(\StripeIntegration\Payments\Cron\WebhooksPing::class);

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
