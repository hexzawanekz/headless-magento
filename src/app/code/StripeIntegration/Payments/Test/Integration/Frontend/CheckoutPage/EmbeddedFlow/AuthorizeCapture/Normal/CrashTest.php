<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\CheckoutPage\EmbeddedFlow\AuthorizeCapture\Normal;

use StripeIntegration\Payments\Test\Integration\Mock\Magento\Sales\Model\Order as MockOrder;
// use StripeIntegration\Payments\Test\Integration\Mock\Plugin\Sales\Model\Service\OrderService as MockOrderService;
use StripeIntegration\Payments\Test\Integration\Mock\Magento\Quote\Model\QuoteManagement as MockQuoteManagement;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class CrashTest extends \PHPUnit\Framework\TestCase
{
    private $objectManager;
    private $quote;
    private $tests;
    private $tokenHelper;

    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();

        $this->objectManager->configure([
            'preferences' => [
                \Magento\Quote\Model\QuoteManagement::class => MockQuoteManagement::class,
                \Magento\Sales\Model\Order::class => MockOrder::class
            ]
        ]);

        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests($this);
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();
        $this->tokenHelper = $this->objectManager->get(\StripeIntegration\Payments\Helper\Token::class);
    }

    /**
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/ApiKeysIsolated.php
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 0
     * @magentoConfigFixture current_store payment/stripe_payments/payment_action authorize_capture
     */
    public function testCrashBeforeOrderSave()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("Normal")
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setPaymentMethod("SuccessCard");

        $ordersCount = $this->tests->getOrdersCount();

        try
        {
            MockOrder::$crashBeforeOrderSave = true;
            $order = $this->quote->placeOrder();
            $this->assertTrue(false);
        }
        catch (\Exception $e)
        {
            $this->assertEquals("crashBeforeOrderSave", $e->getMessage());
        }

        $newOrdersCount = $this->tests->getOrdersCount();
        $this->assertEquals($ordersCount, $newOrdersCount);

        $orderIncrementId = $this->quote->getQuote()->getReservedOrderId();

        $paymentIntents = $this->tests->stripe()->paymentIntents->all(['limit' => 1]);
        $paymentIntentId = $paymentIntents->data[0]->id;

        // Check that there is a successful payment without an order in Magento
        $this->tests->compare($paymentIntents->data[0], [
            "amount" => 5330,
            "metadata" => [
                "Order #" => $orderIncrementId
            ],
            "status" => "succeeded"
        ]);

        // 2nd order placement attempt is successful
        MockOrder::$crashBeforeOrderSave = false;
        // $this->paymentElement->getClientSecret($this->quote->getQuote()->getId()); // Updates the payment intent
        $order = $this->quote->placeOrder();
        $this->assertEquals($orderIncrementId, $order->getIncrementId());

        // Ensure that no new payment was created
        $paymentIntents = $this->tests->stripe()->paymentIntents->all(['limit' => 1]);
        $this->assertEquals($paymentIntentId, $paymentIntents->data[0]->id);
    }

    /**
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 0
     * @magentoConfigFixture current_store payment/stripe_payments/payment_action authorize_capture
     */
    public function testCrashAfterOrderSave()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("Normal")
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setPaymentMethod("SuccessCard");

        $ordersCount = $this->tests->getOrdersCount();

        try
        {
            MockQuoteManagement::$crashAfterOrderSave = true;
            $order = $this->quote->placeOrder();
            $this->assertTrue(false);
        }
        catch (\Exception $e)
        {
            $this->assertEquals("crashAfterOrderSave", $e->getMessage());
        }

        $newOrdersCount = $this->tests->getOrdersCount();

        $this->assertEquals($ordersCount + 1, $newOrdersCount);

        $order = $this->tests->getLastOrder();
        $paymentIntentId = $this->tokenHelper->cleanToken($order->getPayment()->getLastTransId());
        $paymentIntent = $this->tests->stripe()->paymentIntents->retrieve($paymentIntentId, []);
        $this->assertEquals("succeeded", $paymentIntent->status);

        MockQuoteManagement::$crashAfterOrderSave = false;
        try
        {
            $order2 = $this->quote->placeOrder();
            $this->assertTrue(false, "The order was placed successfully, whereas it should have crashed.");
        }
        catch (\Exception $e)
        {
            $this->assertEquals("The order has already been placed and paid.", $e->getMessage());
        }
    }

    /**
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 0
     * @magentoConfigFixture current_store payment/stripe_payments/payment_action authorize_capture
     */
    public function testCrashAfterOrderSaveWithUpdatedCart()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("Normal")
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setPaymentMethod("SuccessCard");

        $ordersCount = $this->tests->getOrdersCount();

        try
        {
            MockQuoteManagement::$crashAfterOrderSave = true;
            $order = $this->quote->placeOrder();
            $this->assertTrue(false);
        }
        catch (\Exception $e)
        {
            $this->assertEquals("crashAfterOrderSave", $e->getMessage());
        }

        $newOrdersCount = $this->tests->getOrdersCount();

        $this->assertEquals($ordersCount + 1, $newOrdersCount);

        $order = $this->tests->getLastOrder();
        $paymentIntentId = $this->tokenHelper->cleanToken($order->getPayment()->getLastTransId());
        $paymentIntent = $this->tests->stripe()->paymentIntents->retrieve($paymentIntentId, []);
        $this->assertEquals("succeeded", $paymentIntent->status);

        MockQuoteManagement::$crashAfterOrderSave = false;
        $this->quote->addProduct('simple-product', 1)->save();

        try
        {
            $this->quote->placeOrder();
            $this->assertTrue(false, "The order was placed successfully, whereas it should have crashed.");
        }
        catch (\Exception $e)
        {
            $this->assertEquals("The order has already been placed and paid.", $e->getMessage());
        }
    }
}
