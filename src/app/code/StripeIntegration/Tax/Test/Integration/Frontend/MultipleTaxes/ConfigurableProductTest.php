<?php

namespace StripeIntegration\Tax\Test\Integration\Frontend\MultipleTaxes;

use StripeIntegration\Tax\Test\Integration\Helper\Calculator;
use StripeIntegration\Tax\Test\Integration\Helper\Compare;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class ConfigurableProductTest extends \PHPUnit\Framework\TestCase
{
    private $objectManager;
    private $quote;
    private $compare;
    private $calculator;
    private $productRepository;

    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->quote = new \StripeIntegration\Tax\Test\Integration\Helper\Quote();
        $this->compare = new Compare($this);
        $this->calculator = new Calculator('California');
        $this->productRepository = $this->objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
    }

    /**
     * @magentoConfigFixture current_store tax/stripe_tax/prices_and_promotions_tax_behavior exclusive
     * @magentoConfigFixture current_store tax/stripe_tax/shipping_tax_behavior exclusive
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Tax/Test/Integration/_files/Data/ApiKeysUS.php
     */
    public function testTaxExclusive()
    {
        $this->runTheTest('exclusive');
    }

    /**
     * @magentoConfigFixture current_store tax/stripe_tax/prices_and_promotions_tax_behavior inclusive
     * @magentoConfigFixture current_store tax/stripe_tax/shipping_tax_behavior inclusive
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Tax/Test/Integration/_files/Data/ApiKeysUS.php
     */
    public function testTaxInclusive()
    {
        $this->runTheTest('inclusive');
    }

    private function runTheTest($taxBehaviour)
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("ConfigurableProduct")
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setPaymentMethod("checkmo");

        $quoteItem = $this->quote->getQuoteItem('simple-product-red');
        $product = $this->productRepository->get($quoteItem->getSku());

        $price = $product->getPrice();
        $quoteData = $this->calculator->calculateQuoteDataMultipleTaxes($price, 1, 5, $taxBehaviour);
        $this->compare->compareQuoteData($this->quote->getQuote(), $quoteData);

        // For the case of the US address which we use to test multiple tax rates on a product at once, shipping is not
        // taxed by default, even if the code for shipping is set to be taxed. Sending 0 as the shipping amount will
        // resolve the calculations
        $quoteItemData = $this->calculator->calculateQuoteItemData($price, 0, 1, $taxBehaviour);
        $this->compare->compareQuoteItemData($quoteItem, $quoteItemData);
    }
}
