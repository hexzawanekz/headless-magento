<?php

namespace StripeIntegration\Payments\Test\Integration;

class ClearTestData extends \PHPUnit\Framework\TestCase
{
    private $tests;

    public function setUp(): void
    {
        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests($this);
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/ApiKeys.php
     */
    public function testClearTestData()
    {
        try
        {
            $this->clear();
        }
        catch (\Exception $e)
        {
            // Ignore
        }
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/ApiKeysLegacy.php
     */
    public function testClearTestDataLegacy()
    {
        try
        {
            $this->clear();
        }
        catch (\Exception $e)
        {
            // Ignore
        }
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/ApiKeysUK.php
     */
    public function testClearTestDataUK()
    {
        try
        {
            $this->clear();
        }
        catch (\Exception $e)
        {
            // Ignore
        }
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/ApiKeysUS.php
     */
    public function testClearTestDataUS()
    {
        try
        {
            $this->clear();
        }
        catch (\Exception $e)
        {
            // Ignore
        }
    }

    protected function clear()
    {
        $this->tests->config()->reInitStripeFromStoreCode("default");
        $subscriptions = $this->tests->stripe()->subscriptions->all(['limit' => 100]);

        foreach ($subscriptions->autoPagingIterator() as $subscription)
        {
            if ($subscription->status == "trialing" || $subscription->status == "active")
            {
                try
                {
                    $this->tests->stripe()->subscriptions->cancel($subscription->id, []);
                }
                catch (\Exception $e)
                {

                }
            }
        }

        $endpoints = $this->tests->stripe()->webhookEndpoints->all(['limit' => 100]);
        foreach ($endpoints->autoPagingIterator() as $endpoint)
        {
            try
            {
                if ($endpoint->status == "enabled")
                {
                    $endpoint->delete();
                }
            }
            catch (\Exception $e)
            {

            }
        }
    }
}
