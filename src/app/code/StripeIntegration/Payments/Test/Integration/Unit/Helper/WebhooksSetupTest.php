<?php

namespace StripeIntegration\Payments\Test\Integration\Unit\Helper;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class WebhooksSetupTest extends \PHPUnit\Framework\TestCase
{
    private $objectManager;

    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
    }

    /**
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 0
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/ApiKeysTestAndLive.php
     */
    public function testCacheInvalidation()
    {
        $webhooksSetup = $this->objectManager->get(\StripeIntegration\Payments\Helper\WebhooksSetup::class);

        $webhooksSetup->configure();

        $this->assertEmpty($webhooksSetup->errorMessages);
        $this->assertNotEmpty($webhooksSetup->successMessages);
    }
}
