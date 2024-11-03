<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\CheckoutPage\EmbeddedFlow\Order\Normal;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class DeletePaymentMethodTest extends \PHPUnit\Framework\TestCase
{
    private $quote;
    private $tests;

    public function setUp(): void
    {
        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests($this);
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();
    }

    /**
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 0
     * @magentoConfigFixture current_store payment/stripe_payments/payment_action order
     * @magentoConfigFixture current_store payment/stripe_payments/automatic_invoicing 0
     */
    public function testNormalCart()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("Normal")
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setPaymentMethod("SuccessCard");

        $order = $this->quote->placeOrder();

        // Refresh the order object
        $order = $this->tests->refreshOrder($order);

        $this->tests->log($order);

        $stripeCustomer = $this->tests->helper()->getCustomerModel();
        $this->assertNotEmpty($stripeCustomer->getStripeId());
        $this->assertEquals(0, $stripeCustomer->getCustomerId());

        $paymentMethodId = $order->getPayment()->getAdditionalInformation('token');
        $this->assertStringStartsWith("pm_", $paymentMethodId);

        try
        {
            $stripeCustomer->deletePaymentMethod($paymentMethodId);
            $this->assertTrue(false, "The payment method should not be deletable");
        }
        catch (\StripeIntegration\Payments\Exception\PaymentMethodInUse $e)
        {

        }
        catch (\Exception $e)
        {
            $this->fail($e->getMessage());
        }
    }
}
