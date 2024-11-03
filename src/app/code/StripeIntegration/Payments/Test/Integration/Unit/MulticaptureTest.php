<?php

namespace StripeIntegration\Payments\Test\Integration\Unit;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class MulticaptureTest extends \PHPUnit\Framework\TestCase
{
    private $tests;

    public function setUp(): void
    {
        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests($this);
    }

    /**
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 0
     */
    public function testGetSubscriptionDetails()
    {
        $this->markTestSkipped('This test only runs on IC+ accounts.');

        $paymentIntent = $this->tests->stripe()->paymentIntents->create([
            'amount' => 1000,
            'currency' => 'usd',
            'payment_method' => 'pm_card_visa',
            'confirm' => true,
            'capture_method' => 'manual',
            'payment_method_options' => [
                'card' => [
                    'request_multicapture' => 'if_available'
                ]
            ]
        ]);

        $this->tests->stripe()->paymentIntents->capture($paymentIntent->id, [
            'amount_to_capture' => 700,
            'final_capture' => false
        ]);

        $this->tests->stripe()->paymentIntents->capture($paymentIntent->id, [
            'amount_to_capture' => 0,
            'final_capture' => true
        ]);

    }
}
