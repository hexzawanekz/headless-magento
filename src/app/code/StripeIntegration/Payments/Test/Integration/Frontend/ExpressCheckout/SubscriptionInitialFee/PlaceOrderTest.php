<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\ExpressCheckout\Subscription;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class PlaceOrderTest extends \PHPUnit\Framework\TestCase
{
    private $apiService;
    private $objectManager;
    private $tests;

    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests($this);
        $this->apiService = $this->objectManager->get(\StripeIntegration\Payments\Api\Service::class);
    }

    /**
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 0
     */
    public function testPlaceOrder()
    {
        $product = $this->tests->helper()->loadProductBySku("simple-monthly-subscription-initial-fee-product");
        $request = [
            "product" => $product->getId(),
            "related_product" => "",
            "qty" => 1
        ];
        $result = $this->apiService->addtocart($request);
        $this->assertEquals("[]", $result);

        $address = $this->tests->address()->getExpressCheckoutElementFormat("NewYork");

        $result = $this->apiService->ece_shipping_address_changed($address, "checkout");
        $this->assertNotEmpty($result);

        $data = json_decode($result, true);
        $this->assertNotEmpty($data["resolvePayload"]['lineItems']);
        $this->assertNotEmpty($data["resolvePayload"]['shippingRates']);

        $selectedShippingMethod = $data["resolvePayload"]['shippingRates'][0];
        $result = $this->apiService->ece_shipping_rate_changed($address, $selectedShippingMethod["id"]);
        $this->assertNotEmpty($result);

        $data = json_decode($result, true);
        $this->assertNotEmpty($data["resolvePayload"]['lineItems']);
        $this->assertNotEmpty($data["resolvePayload"]['shippingRates']);

        // Check that the intial fee is in the line items
        $foundInitialFee = false;
        foreach ($data['resolvePayload']['lineItems'] as $item) {
            if ($item['name'] === 'Initial Fee' && $item['amount'] === 300) {
                $foundInitialFee = true;
                break;
            }
        }
        $this->assertTrue($foundInitialFee, 'No line item with name "Initial Fee" and amount of 300 found.');

        $stripe = $this->tests->stripe();
        $confirmationToken = $stripe->testHelpers->confirmationTokens->create([
            'payment_method' => 'pm_card_visa',
            'setup_future_usage' => 'off_session'
        ]);
        $this->assertNotEmpty($confirmationToken);
        $this->assertNotEmpty($confirmationToken->id);

        $address = $this->tests->address()->getStripeFormat("NewYork");
        $result = [
            "elementType" => "expressCheckout",
            "expressPaymentType" => "link",
            "billingDetails" => $address,
            "shippingAddress" => $address,
            "shippingRate" =>  $selectedShippingMethod,
            "confirmationToken" =>  $confirmationToken
        ];

        $result = $this->apiService->place_order($result, "product");
        $this->assertNotEmpty($result);

        $data = json_decode($result, true);
        $this->assertNotEmpty($data);
        $this->assertNotEmpty($data["redirect"]);
        $this->assertStringContainsString("checkout/onepage/success", $data["redirect"]);

        // Load the order
        $session = $this->objectManager->get(\Magento\Checkout\Model\Session::class);
        $this->assertNotEmpty($session->getLastRealOrderId());
        $orderIncrementId = $session->getLastRealOrderId();
        $order = $this->tests->getLastOrder();
        $this->assertEquals($orderIncrementId, $order->getIncrementId());

        // Load the payment intent
        $paymentIntentId = $order->getPayment()->getLastTransId();
        $this->assertNotEmpty($paymentIntentId);
        $paymentIntent = $this->tests->stripe()->paymentIntents->retrieve($paymentIntentId);

        // Check if Radar risk value is exist in the payment object
        $this->assertNotEmpty($paymentIntent->charges->data[0]->outcome->risk_score);
        $this->assertNotEmpty($paymentIntent->charges->data[0]->outcome->risk_level);

        // Stripe checks
        $this->assertEquals($order->getGrandTotal() * 100, $paymentIntent->amount);
        $this->assertCount(1, $paymentIntent->charges->data);
        $this->assertEquals($order->getGrandTotal() * 100, $paymentIntent->charges->data[0]->amount);
        $this->assertEquals("succeeded", $paymentIntent->charges->data[0]->status);
        $this->assertEquals("Subscription order #$orderIncrementId by Flint Jerry", $paymentIntent->description);
        $this->assertEquals($orderIncrementId, $paymentIntent->metadata->{"Order #"});

        // Trigger webhook events
        $this->tests->event()->triggerPaymentIntentEvents($paymentIntent, $this);

        // Refresh the order object
        $order = $this->tests->refreshOrder($order);

        // Check if Radar risk value is been set to the order
        $this->assertIsNumeric($order->getStripeRadarRiskScore());
        $this->assertGreaterThanOrEqual(0, $order->getStripeRadarRiskScore());
        $this->assertNotEquals('NA', $order->getStripeRadarRiskLevel());

        // Check Stripe Payment method
        $paymentMethod = $this->tests->loadPaymentMethod($order->getId());
        $this->assertEquals('card', $paymentMethod->getPaymentMethodType());

        // Stripe checks
        // $paymentIntent = $this->tests->stripe()->paymentIntents->retrieve($paymentIntentId);

    }
}
