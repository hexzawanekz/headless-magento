<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\CheckoutPage\EmbeddedFlow\AuthorizeOnly\ManualInvoicing;

use StripeIntegration\Payments\Exception\PaymentMethodInUse;

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
     * @magentoConfigFixture current_store payment/stripe_payments/payment_action authorize
     * @magentoConfigFixture current_store payment/stripe_payments/expired_authorizations 1
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
        $paymentIntent = $this->tests->confirm($order);
        $this->tests->log($paymentIntent);

        // Refresh the order object
        $order = $this->tests->refreshOrder($order);

        $stripeCustomer = $this->tests->helper()->getCustomerModel();
        $this->assertNotEmpty($stripeCustomer->getStripeId());
        $this->assertEquals(0, $stripeCustomer->getCustomerId());

        $stripePaymentMethod = $paymentIntent->payment_method;
        $this->assertEquals($stripeCustomer->getStripeId(), $stripePaymentMethod->customer);

        try
        {
            $stripeCustomer->deletePaymentMethod($stripePaymentMethod->id);
            $this->assertTrue(false, "The payment method should not be deletable");
        }
        catch (PaymentMethodInUse $e)
        {

        }
        catch (\Exception $e)
        {
            $this->fail($e->getMessage());
        }
    }
}
