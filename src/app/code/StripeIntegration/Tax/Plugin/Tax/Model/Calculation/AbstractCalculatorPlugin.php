<?php

namespace StripeIntegration\Tax\Plugin\Tax\Model\Calculation;

use Magento\Tax\Api\Data\AppliedTaxInterfaceFactory;
use Magento\Tax\Api\Data\AppliedTaxRateInterfaceFactory;
use Magento\Tax\Api\Data\QuoteDetailsItemInterface;
use Magento\Tax\Api\Data\TaxDetailsItemInterfaceFactory;
use Magento\Tax\Model\Calculation\AbstractCalculator;
use StripeIntegration\Tax\Helper\Currency;
use StripeIntegration\Tax\Helper\Logger;
use StripeIntegration\Tax\Helper\Store;
use StripeIntegration\Tax\Helper\TaxCalculator;
use StripeIntegration\Tax\Model\StripeTax;
use StripeIntegration\Tax\Model\TaxFlow;

class AbstractCalculatorPlugin
{
    private $stripeTax;
    private $taxDetailsItemDataObjectFactory;
    private $currencyHelper;
    private $appliedTaxRatesObjectFactory;
    private $appliedTaxDataObjectFactory;
    private $storeHelper;
    private $taxCalculatorHelper;
    private $taxFlow;
    private $logger;

    public function __construct(
        StripeTax $stripeTax,
        TaxDetailsItemInterfaceFactory $taxDetailsItemDataObjectFactory,
        Currency $currencyHelper,
        AppliedTaxRateInterfaceFactory $appliedTaxRatesObjectFactory,
        AppliedTaxInterfaceFactory $appliedTaxDataObjectFactory,
        Store $storeHelper,
        TaxCalculator $taxCalculatorHelper,
        TaxFlow $taxFlow,
        Logger $logger
    )
    {
        $this->stripeTax = $stripeTax;
        $this->taxDetailsItemDataObjectFactory = $taxDetailsItemDataObjectFactory;
        $this->currencyHelper = $currencyHelper;
        $this->appliedTaxRatesObjectFactory = $appliedTaxRatesObjectFactory;
        $this->appliedTaxDataObjectFactory = $appliedTaxDataObjectFactory;
        $this->storeHelper = $storeHelper;
        $this->taxCalculatorHelper = $taxCalculatorHelper;
        $this->taxFlow = $taxFlow;
        $this->logger = $logger;
    }

    public function aroundCalculate(
        AbstractCalculator $subject,
        callable $proceed,
        QuoteDetailsItemInterface $item,
        $quantity,
        $round = true
    )
    {
        if ($this->stripeTax->isEnabled() && $item->getIsStripePrepared()) {
            $calculatedValues = $this->taxCalculatorHelper->getZeroValuesPrices();
            $appliedTaxes = $this->getZeroValuesAppliedTaxes();
            try {
                $baseCurrency = $this->storeHelper->getCurrentStore()->getBaseCurrencyCode();
                $currentCurrency = $this->storeHelper->getCurrentStore()->getCurrentCurrency();
                $stripeValues = $this->taxCalculatorHelper->getStripeCalculatedValues($item, $currentCurrency, $baseCurrency);
                $calculatedValues = $this->taxCalculatorHelper->calculatePrices($item, $stripeValues, $quantity);
                $appliedTaxes = $this->getAppliedTaxes($item->getStripeAppliedTaxes(), $stripeValues['currency']);
            } catch (\Exception $e) {
                $this->logger->debug(
                    'Issue encountered at item calculation step:' . PHP_EOL . $e->getMessage(),
                    $e->getTraceAsString()
                );
                $this->taxFlow->orderItemCalculationIssues = true;
            }

            return $this->taxDetailsItemDataObjectFactory->create()
                ->setCode($item->getCode())
                ->setType($item->getType())
                ->setRowTax($calculatedValues['row_tax'])
                ->setPrice($calculatedValues['price'])
                ->setPriceInclTax($calculatedValues['price_incl_tax'])
                ->setRowTotal($calculatedValues['row_total'])
                ->setRowTotalInclTax($calculatedValues['row_total_incl_tax'])
                ->setDiscountTaxCompensationAmount($calculatedValues['discount_tax_compensation'])
                ->setAssociatedItemCode($item->getAssociatedItemCode())
                ->setTaxPercent($appliedTaxes['total_tax_percent'])
                ->setAppliedTaxes($appliedTaxes['object']);
        }

        return $proceed($item, $quantity, $round);
    }

    private function getAppliedTaxes($taxBreakdown, $currency)
    {
        $appliedTaxes['object'] = [];
        $totalTaxPercent = 0;
        foreach ($taxBreakdown as $taxLevel => $taxRates) {
            foreach ($taxRates as $taxRate) {
                $rateObject = $this->appliedTaxRatesObjectFactory->create();
                $rateObject->setCode($taxRate['code']);
                $rateObject->setPercent($taxRate['percent']);
                $rateObject->setTitle($taxRate['title']);

                $dataObject = $this->appliedTaxDataObjectFactory->create();
                $dataObject->setAmount($this->currencyHelper->stripeAmountToMagentoAmount($taxRate['amount'], $currency));
                $dataObject->setPercent($taxRate['percent']);
                $dataObject->setTaxRateKey($taxRate['code']);
                $dataObject->setRates([$rateObject]);

                $totalTaxPercent += $taxRate['percent'];

                $appliedTaxes['object'][] = $dataObject;
            }
        }
        $appliedTaxes['total_tax_percent'] = $totalTaxPercent;

        return $appliedTaxes;
    }

    private function getZeroValuesAppliedTaxes()
    {
        $appliedTaxes['object'] = [];
        $appliedTaxes['total_tax_percent'] = 0;

        return $appliedTaxes;
    }
}