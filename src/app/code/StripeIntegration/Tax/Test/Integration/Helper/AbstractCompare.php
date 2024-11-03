<?php

namespace StripeIntegration\Tax\Test\Integration\Helper;

use Psr\Log\LoggerInterface;
use StripeIntegration\Tax\Helper\Logger;

/**
 * this class has the common comparisons for Order, Invoice and Quote.
 * Will be extended by the child class if we will need to add new specific comparisons and to keep
 * the quote comparison methods which already exist so as not to change all the tests
 */
class AbstractCompare
{
    private $test;

    public function __construct($test)
    {
        $this->test = $test;
    }

    public function compareGeneralData($object, $calculatedData, $entity = 'Quote')
    {
        $objectData = $object->getData();
        foreach ($calculatedData as $key => $expectedValue) {
            $this->assertValues($expectedValue, $objectData[$key], $entity . " '$key' field:");
        }
    }

    public function compareItemData($item, $calculatedData, $entity = 'Quote')
    {
        $itemData = $item->getData();
        foreach ($calculatedData as $key => $expectedValue) {
            $this->assertValues($expectedValue, $itemData[$key], $entity . " item '$key' field:");
        }
    }

    public function compareShippingData($address, $calculatedData)
    {
        $addressData = $address->getData();
        foreach ($calculatedData as $key => $expectedValue) {
            $this->assertValues($expectedValue, $addressData[$key], "Shipping '$key' field:");
        }
    }

    public function getTest()
    {
        return $this->test;
    }

    /**
     * @param $expected
     * @param $actual
     * @param $message
     *
     * Because we are not able to replicate the Stripe tax calculation algorithm exactly, there are cases where some
     * expected values might differ with a maximum of 0.02 delta from what comes directly from the API.
     * The purpose of this method is that if the expected and actual values differ, they are tested for a delta.
     * The test will fail if the delta is more than 0.02 at the moment.
     * The other assertions are made as per usual for a test.
     *
     * @return void
     */
    public function assertValues($expected, $actual, $message)
    {
        if ($expected != $actual) {
            // For debugging purposes we can use xdebug or simply echo the deltas and other values in case
            // tests where the expected and actual values differ from the set delta
            $this->test->assertEqualsWithDelta($expected, $actual, 0.02, $message . ' more than 0.02');
        } else {
            $this->test->assertEquals($expected, $actual, $message);
        }
    }
}