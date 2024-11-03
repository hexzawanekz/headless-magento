<?php

namespace StripeIntegration\Payments\Test\Integration\Unit\Model;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class PaymentIntentTest extends \PHPUnit\Framework\TestCase
{
    private $objectManager;
    private $paymentIntentModel;
    private $quote;
    private $tests;

    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();
        $this->paymentIntentModel = $this->objectManager->get(\StripeIntegration\Payments\Model\PaymentIntent::class);
        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests($this);
    }

    // It should be possible to get subscription params without a quote
    public function testGetParamsFrom()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("SubscriptionInitialFee")
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setPaymentMethod("SuccessCard");

        $order = $this->quote->placeOrder();

        $params = $this->paymentIntentModel->getParamsFrom($order);

        $this->assertNotEmpty($params["customer"]);
        $this->assertNotEmpty($params["payment_method"]);

        $this->tests->compare($params, [
            "amount" => 325, // Initial fee + tax
            "currency" => "usd",
            "description" => "Subscription order #{$order->getIncrementId()} by Joyce Strother",
            "metadata" => [
                "Order #" => $order->getIncrementId()
            ],
            "shipping" => [
                "address" => [
                    "line1" => "2974 Providence Lane",
                    "city" => "Mira Loma",
                    "country" => "US",
                    "postal_code" => "91752",
                    "state" => "California"
                ],
                "name" => "Joyce Strother",
                "phone" => "626-945-7637"
            ]
        ]);
    }
}
