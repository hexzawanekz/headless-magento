<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\Tax;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class TaxDisableTest extends \PHPUnit\Framework\TestCase
{
    public function setUp(): void
    {
    }

    /**
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Tax/TaxDisable.php
     */
    public function testDisable()
    {
    }
}