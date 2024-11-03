<?php

namespace StripeIntegration\Tax\Test\Integration;

class Seed extends \PHPUnit\Framework\TestCase
{
    /**
     * @magentoAppIsolation disabled
     * @magentoDbIsolation disabled
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Tax/Test/Integration/_files/Data/Enable.php
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Tax/Test/Integration/_files/Data/ApiKeys.php
     */
    public function testDatabaseSetup()
    {
        // Run this test only once to seed the database with products, taxes etc
    }
}
