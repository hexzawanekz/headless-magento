<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\Tax\SubscriptionInitialFee;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class DifferentCurrencyTaxExclusiveTest extends \PHPUnit\Framework\TestCase
{
    private $quote;

    public function setUp(): void
    {
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();
    }

    /**
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Tax/Test/Integration/_files/Data/Enable.php
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Tax/TaxApiKeys.php
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Tax/TaxClasses.php
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Tax/ExchangeRatesEURUSD.php
     * @magentoConfigFixture current_store tax/stripe_tax/prices_and_promotions_tax_behavior exclusive
     * @magentoConfigFixture current_store tax/stripe_tax/shipping_tax_behavior exclusive
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 0
     * @magentoConfigFixture current_store payment/stripe_payments/save_payment_method 0
     * @magentoConfigFixture current_store payment/stripe_payments/payment_action authorize_capture
     * @magentoConfigFixture current_store currency/options/base USD
     * @magentoConfigFixture current_store currency/options/allow EUR,USD
     * @magentoConfigFixture current_store currency/options/default EUR
     */
    public function testInitialFees()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("SubscriptionInitialFee")
            ->setShippingAddress("Romania")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("Romania")
            ->setPaymentMethod("SuccessCard");

        $order = $this->quote->placeOrder();

        $orderItem = null;

        foreach ($order->getAllItems() as $item)
        {
            if ($item->getSku() == "simple-monthly-subscription-initial-fee-product")
            {
                $orderItem = $item;
            }
        }

        $this->assertEquals(2.55, $orderItem->getInitialFee());
        $this->assertEquals(3, $orderItem->getBaseInitialFee());
        $this->assertEquals(0.49, $orderItem->getInitialFeeTax());
        $this->assertEquals(0.58, $orderItem->getBaseInitialFeeTax());
    }
}