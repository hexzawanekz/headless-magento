<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\ExpressCheckout\Normal;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class AuthenticationRequiredTest extends \PHPUnit\Framework\TestCase
{
    private $apiService;
    private $helper;
    private $objectManager;
    private $stripeConfig;
    private $tests;

    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests($this);
        $this->helper = $this->objectManager->get(\StripeIntegration\Payments\Helper\Generic::class);
        $this->apiService = $this->objectManager->get(\StripeIntegration\Payments\Api\Service::class);
        $this->stripeConfig = $this->objectManager->get(\StripeIntegration\Payments\Model\Config::class);
    }

    /**
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 0
     */
    public function test3DS()
    {
        $product = $this->helper->loadProductBySku("simple-product");
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

        $stripe = $this->stripeConfig->getStripeClient();
        $confirmationToken = $stripe->testHelpers->confirmationTokens->create([
            'payment_method' => 'pm_card_authenticationRequired'
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

        try
        {
            $result = $this->apiService->place_order($result, "product");
        }
        catch (\Exception $e)
        {
            $this->assertStringContainsString("Authentication Required: pi_", $e->getMessage());
        }
    }
}
