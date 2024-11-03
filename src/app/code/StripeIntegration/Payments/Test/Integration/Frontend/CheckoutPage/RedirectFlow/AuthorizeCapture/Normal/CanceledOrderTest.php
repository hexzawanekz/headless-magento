<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\RedirectFlow\AuthorizeCapture\Normal;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class CanceledOrderTest extends \PHPUnit\Framework\TestCase
{
    private $quote;
    private $tests;
    private $service;
    private $objectManager;

    public function setUp(): void
    {
        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests($this);
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->service = $this->objectManager->get(\StripeIntegration\Payments\Api\Service::class);
    }

    /**
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 1
     * @magentoConfigFixture current_store payment/stripe_payments/save_payment_method 0
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/ApiKeysLegacy.php

     */
    public function testGetCheckoutSessionId()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("Normal")
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setPaymentMethod("StripeCheckout");

        $order = $this->quote->placeOrder();

        // Get session id
        $checkoutSessionId = $this->service->get_checkout_session_id();

        // Refresh the order
        $order = $this->tests->refreshOrder($order);

        // Assert the order state is pending_payment
        $this->assertEquals("pending_payment", $order->getState());

        // Assert the order status is pending_payment
        $this->assertEquals("pending_payment", $order->getStatus());

    }
}