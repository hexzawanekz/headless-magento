<?php

namespace StripeIntegration\Tax\Helper;

class TaxCalculator
{
    private $currencyHelper;

    public function __construct(
        Currency $currencyHelper
    )
    {
        $this->currencyHelper = $currencyHelper;
    }
    public function getFormattedStripeCalculatedValues($tax, $amount, $currency)
    {
        return [
            'stripe_tax' => $tax,
            'stripe_amount' => $amount,
            'currency' => $currency
        ];
    }

    public function calculateStripeValuesInBaseCurrency($item, $currentCurrency, $baseCurrencyCode)
    {
        $rate = $currentCurrency->getAnyRate($baseCurrencyCode);
        $stripeTotalCalculatedTax = round($item->getStripeTotalCalculatedTax() * $rate);
        $stripeTotalCalculatedAmount = round($item->getStripeTotalCalculatedAmount() * $rate);

        return $this->getFormattedStripeCalculatedValues($stripeTotalCalculatedTax, $stripeTotalCalculatedAmount, $baseCurrencyCode);
    }

    /**
     * @param $item
     * @param $baseToOrderRate
     * @param $baseCurrency
     * @return array
     *
     * Returns the calculated values based on the base-to-order rate saved on the order.
     * Added this because currency rates are changed frequently, and we need to have the values on the order instead
     * of current values which might differ.
     */
    public function calculateStripeValuesInBaseCurrencyForInvoice($item, $baseToOrderRate, $baseCurrencyCode)
    {
        // Because we get the base-to-order rate and because we need the order-to-base rate we will invert it.
        $rate = round(1 / $baseToOrderRate, 4);
        $stripeTotalCalculatedTax = round($item->getStripeTotalCalculatedTax() * $rate);
        $stripeTotalCalculatedAmount = round($item->getStripeTotalCalculatedAmount() * $rate);

        return $this->getFormattedStripeCalculatedValues($stripeTotalCalculatedTax, $stripeTotalCalculatedAmount, $baseCurrencyCode);
    }

    public function getStripeCalculatedValues($item, $currentCurrency, $baseCurrencyCode)
    {
        if ($item->getUseBaseCurrency()) {
            return $this->calculateStripeValuesInBaseCurrency($item, $currentCurrency, $baseCurrencyCode);
        } else {
            return $this->getFormattedStripeCalculatedValues(
                $item->getStripeTotalCalculatedTax(),
                $item->getStripeTotalCalculatedAmount(),
                $item->getStripeCurrency()
            );
        }
    }

    public function getStripeCalculatedValuesForInvoice($item, $baseToOrderRate, $baseCurrencyCode)
    {
        if ($item->getUseBaseCurrency()) {
            return $this->calculateStripeValuesInBaseCurrencyForInvoice($item, $baseToOrderRate, $baseCurrencyCode);
        } else {
            return $this->getFormattedStripeCalculatedValues(
                $item->getStripeTotalCalculatedTax(),
                $item->getStripeTotalCalculatedAmount(),
                $item->getStripeCurrency()
            );
        }
    }

    public function calculatePrices($item, $calculatedValues, $quantity)
    {
        if ($item->getPriceIncludesTax()) {
            return $this->getTaxInclusiveCalculation($item, $calculatedValues, $quantity);
        } else {
            return $this->getTaxExclusiveCalculation($item, $calculatedValues, $quantity);
        }
    }

    private function getTaxInclusiveCalculation($item, $calculatedValues, $quantity)
    {
        $unitPrice = $this->currencyHelper->magentoAmountToStripeAmount($item->getUnitPrice(), $calculatedValues['currency']);
        $discountAmount = $this->currencyHelper->magentoAmountToStripeAmount($item->getDiscountAmount(), $calculatedValues['currency']);
        if (!$discountAmount) {
            return $this->getTaxInclusivePrices($calculatedValues, $quantity);
        } else {
            return $this->getTaxInclusivePricesWithDiscount($calculatedValues, $quantity, $unitPrice);
        }
    }

    private function getTaxExclusiveCalculation($item, $calculatedValues, $quantity)
    {
        $unitPrice = $this->currencyHelper->magentoAmountToStripeAmount($item->getUnitPrice(), $calculatedValues['currency']);
        $discountAmount = $this->currencyHelper->magentoAmountToStripeAmount($item->getDiscountAmount(), $calculatedValues['currency']);
        if (!$discountAmount) {
            return $this->getTaxExclusivePrices($calculatedValues, $quantity);
        } else {
            return $this->getTaxExclusivePricesWithDiscount($calculatedValues, $quantity, $unitPrice);
        }
    }

    public function getTaxInclusivePrices($calculatedValues, $quantity)
    {
        $rowTax = $calculatedValues['stripe_tax'];
        $rowTotal = $calculatedValues['stripe_amount'] - $rowTax;
        $price = round(($rowTotal) / $quantity);
        $tax = round($rowTax / $quantity);
        $priceInclTax = round($calculatedValues['stripe_amount'] / $quantity);
        $rowTotalInclTax = $calculatedValues['stripe_amount'];
        $discountTaxCompensation = 0;

        return $this->getFormattedPrices(
            $rowTax, $rowTotal, $price, $tax, $priceInclTax, $rowTotalInclTax, $discountTaxCompensation, $calculatedValues['currency']
        );
    }

    public function getTaxInclusivePricesWithDiscount($calculatedValues, $quantity, $unitPrice)
    {
        // for discounts, Magento mainly sets values which contain the full price, so we will use the unit price
        // provided by Magento for these calculations
        $priceInclTax = $unitPrice;
        $rowTotalInclTax = $unitPrice * $quantity;
        $rowTaxAfterDiscount = $calculatedValues['stripe_tax'];
        $rowTax = $this->calculateWithRuleOfThree($calculatedValues['stripe_amount'], $rowTotalInclTax, $calculatedValues['stripe_tax']);
        $rowTotal = $rowTotalInclTax - $rowTax;
        $price = round(($rowTotal) / $quantity);
        $discountTaxCompensation = $rowTax - $rowTaxAfterDiscount;
        $rowTax = $rowTaxAfterDiscount;
        $tax = round($rowTax / $quantity);

        return $this->getFormattedPrices(
            $rowTax, $rowTotal, $price, $tax, $priceInclTax, $rowTotalInclTax, $discountTaxCompensation, $calculatedValues['currency']
        );
    }

    public function getTaxExclusivePrices($calculatedValues, $quantity)
    {
        $rowTax = $calculatedValues['stripe_tax'];
        $rowTotal = $calculatedValues['stripe_amount'];
        $price = round($rowTotal / $quantity);
        $tax = round($rowTax / $quantity);
        $priceInclTax = $price + $tax;
        $rowTotalInclTax = $rowTotal + $rowTax;

        return $this->getFormattedPrices(
            $rowTax, $rowTotal, $price, $tax, $priceInclTax, $rowTotalInclTax, 0, $calculatedValues['currency']
        );
    }

    public function getTaxExclusivePricesWithDiscount($calculatedValues, $quantity, $unitPrice)
    {
        // for discounts, Magento mainly sets values which contain the full price, so we will use the unit price
        // provided by Magento for these calculations
        $rowTax = $calculatedValues['stripe_tax'];
        $rowTotal = $unitPrice * $quantity;
        $price = $unitPrice;
        $tax = round($rowTax / $quantity);
        $rowTaxBeforeDiscount = $this->calculateWithRuleOfThree($calculatedValues['stripe_amount'], $rowTotal, $rowTax);
        $taxBeforeDiscount = round($rowTaxBeforeDiscount / $quantity);
        $priceInclTax = $price + $taxBeforeDiscount;
        $rowTotalInclTax = $priceInclTax * $quantity;

        return $this->getFormattedPrices(
            $rowTax, $rowTotal, $price, $tax, $priceInclTax, $rowTotalInclTax, 0, $calculatedValues['currency']
        );
    }

    public function getFormattedPrices($rowTax, $rowTotal, $price, $tax, $priceInclTax, $rowTotalInclTax, $discountTaxCompensation, $currency)
    {
        return [
            'row_tax' => $this->currencyHelper->stripeAmountToMagentoAmount($rowTax, $currency),
            'row_total' => $this->currencyHelper->stripeAmountToMagentoAmount($rowTotal, $currency),
            'price' => $this->currencyHelper->stripeAmountToMagentoAmount($price, $currency),
            'tax' => $this->currencyHelper->stripeAmountToMagentoAmount($tax, $currency),
            'price_incl_tax' => $this->currencyHelper->stripeAmountToMagentoAmount($priceInclTax, $currency),
            'row_total_incl_tax' => $this->currencyHelper->stripeAmountToMagentoAmount($rowTotalInclTax, $currency),
            'discount_tax_compensation' => $this->currencyHelper->stripeAmountToMagentoAmount($discountTaxCompensation, $currency),
        ];
    }

    public function getZeroValuesPrices()
    {
        return $this->getFormattedPrices(0, 0, 0, 0, 0, 0, 0, 0);
    }

    /**
     * If using a rule, term1 corresponds to term2 and term3 corresponds to x, then x = term2 * term3 / term1.
     *
     * Added the method for the case where there are discounts, and we know the discounted price, the discounted price
     * tax and the normal price. In order to not perform another API call, we would deduce the tax for the normal price
     * based on the existing 3 values. This in turn will help to get the discount tax compensation.
     */
    private function calculateWithRuleOfThree($term1, $term2, $term3)
    {
        if ($term1 == 0) {
            return 0;
        }

        return round($term2 * $term3 / $term1);
    }
}