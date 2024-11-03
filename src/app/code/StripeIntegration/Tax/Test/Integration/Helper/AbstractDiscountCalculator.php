<?php

namespace StripeIntegration\Tax\Test\Integration\Helper;

/**
 * This class holds the common calculations for Quote, Order and Invoice in case of discounts.
 * Will be extended by the child class if we will need to add new specific calculations and to keep
 * the quote calculation methods which already exist so as not to change all the tests
 */
class AbstractDiscountCalculator
{
    private $objectManager;
    private $taxRate;
    private $rateHelper;
    private $stripeTaxCalculator;

    public function __construct($country)
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->rateHelper = $this->objectManager->get(\StripeIntegration\Tax\Test\Integration\Helper\Rate::class);
        $this->stripeTaxCalculator = $this->objectManager->get(\StripeIntegration\Tax\Test\Integration\Helper\StripeTaxCalculator::class);
        switch ($country) {
            case 'Romania':
                $this->taxRate = $this->rateHelper->getTaxRate($country);
                break;
            case 'US':
                $this->taxRate = 10;
                break;
            default:
                $this->taxRate = 25;
                break;
        }
    }

    public function calculateData($product, $qty, $shippingRate,  $taxBehaviour)
    {
        if ($taxBehaviour == 'exclusive') {
            return $this->calculateExclusiveData($product, $qty, $shippingRate);
        } elseif ($taxBehaviour == 'inclusive') {
            return $this->calculateInclusiveData($product, $qty, $shippingRate);
        } else {
            return $this->calculateFreeData($product, $qty, $shippingRate);
        }
    }

    private function calculateExclusiveData($price, $qty, $shippingRate)
    {
        $totalExclTax = ($price + $shippingRate) * $qty;
        $tax = round($this->taxRate / 100 * $totalExclTax, 2);
        return [
            'grand_total' => round($totalExclTax + $tax, 2)
        ];
    }

    private function calculateInclusiveData($price, $qty, $shippingRate)
    {
        $grandTotal = ($price + $shippingRate) * $qty;
        return [
            'grand_total' => $grandTotal
        ];
    }

    private function calculateFreeData($price, $qty, $shippingRate)
    {
        $grandTotal = ($price + $shippingRate) * $qty;
        return [
            'grand_total' => $grandTotal
        ];
    }

    public function calculateItemData($price, $discountedPrice, $shipping, $qty, $taxBehaviour, $otherPrices = null, $isLargestPrice = true)
    {
        $stripeCalculatedData = $this->stripeTaxCalculator->calculateForPrice(
            $discountedPrice * $qty,
            $shipping,
            $this->taxRate,
            $taxBehaviour,
            $otherPrices,
            $isLargestPrice
        );
        if ($taxBehaviour == 'exclusive') {
            return $this->calculateExclusiveItemData($price, $discountedPrice, $qty, $stripeCalculatedData);
        } else {
            return $this->calculateInclusiveItemData($price, $discountedPrice, $qty, $stripeCalculatedData);
        }
    }

    private function calculateExclusiveItemData($price, $discountedPrice, $qty, $stripeCalculatedData)
    {
        $taxAmount = $stripeCalculatedData['tax'];
        $rowTotal = $price * $qty;
        $rowTaxBeforeDiscount = $this->calculateWithRuleOfThree($stripeCalculatedData['amount'], $rowTotal, $taxAmount);
        $taxBeforeDiscount = round($rowTaxBeforeDiscount / $qty, 2);
        $priceInclTax = $price + $taxBeforeDiscount;
        $rowTotalInclTax = $priceInclTax * $qty;

        return [
            'price' => $price,
            'row_total' => $rowTotal,
            'tax_amount' => $taxAmount,
            'price_incl_tax' => $priceInclTax,
            'row_total_incl_tax' => $rowTotalInclTax
        ];
    }

    private function calculateInclusiveItemData($price, $discountedPrice, $qty, $stripeCalculatedData)
    {
        $priceInclTax = $price;
        $rowTotalInclTax = $price * $qty;
        $taxAmount = $stripeCalculatedData['tax'];
        $rowTaxBeforeDiscount = $this->calculateWithRuleOfThree($stripeCalculatedData['amount'], $rowTotalInclTax, $stripeCalculatedData['tax']);
        $rowTotal = round($rowTotalInclTax - $rowTaxBeforeDiscount, 2);
        $price = round($rowTotal / $qty, 2);
        $discountCompensationAmount = round($rowTaxBeforeDiscount - $taxAmount, 2);

        return [
            'price' => $price,
            'row_total' => $rowTotal,
            'tax_amount' => $taxAmount,
            'price_incl_tax' => $priceInclTax,
            'row_total_incl_tax' => $rowTotalInclTax,
            'discount_tax_compensation_amount' => $discountCompensationAmount
        ];
    }

    private function getUnRoundedTaxAmount($taxAmount)
    {
        $newTax = $taxAmount * 100;

        return (int)$newTax / 100;
    }

    public function calculateShippingData($price, $discountedPrice, $qty, $taxBehaviour)
    {
        $stripeCalculatedData = $this->stripeTaxCalculator->calculateForShipping(
            $price,
            $discountedPrice,
            $this->taxRate,
            $taxBehaviour
        );
        if ($taxBehaviour == 'exclusive') {
            return $this->calculateExclusiveShippingData($price, $discountedPrice, $qty, $stripeCalculatedData);
        } elseif ($taxBehaviour == 'inclusive') {
            return $this->calculateInclusiveShippingData($price, $discountedPrice, $qty, $stripeCalculatedData);
        } else {
            return $this->calculateFreeShippingData($price, $qty, $stripeCalculatedData);
        }
    }

    private function calculateExclusiveShippingData($price, $discountedPrice, $qty, $stripeCalculatedData)
    {
        $shippingAmount = $price * $qty;
        $taxAmount = $stripeCalculatedData['tax'];
        $taxBeforeDiscount = $this->calculateWithRuleOfThree($stripeCalculatedData['amount'], $shippingAmount, $taxAmount);
        $shippingInclTax = round($shippingAmount + $taxBeforeDiscount, 2);
        return [
            'shipping_amount' => $shippingAmount,
            'shipping_tax_amount' => $taxAmount,
            'shipping_incl_tax' => $shippingInclTax
        ];
    }

    private function calculateInclusiveShippingData($price, $discountedPrice, $qty, $stripeCalculatedData)
    {
        $shippingInclTax = $price * $qty;
        $taxAmount = $stripeCalculatedData['tax'];
        $taxAmountBeforeDiscount = $this->calculateWithRuleOfThree($stripeCalculatedData['amount'], $shippingInclTax, $taxAmount);
        $shippingAmount = round($shippingInclTax - $taxAmountBeforeDiscount, 2);
        $taxCompensationAmount = round($taxAmountBeforeDiscount - $taxAmount, 2);
        return [
            'shipping_amount' => $shippingAmount,
            'shipping_tax_amount' => $taxAmount,
            'shipping_incl_tax' => $shippingInclTax,
            'shipping_discount_tax_compensation_amount' => $taxCompensationAmount
        ];
    }

    private function calculateFreeShippingData($price, $qty, $stripeCalculatedData)
    {
        $shippingAmount = $price * $qty;
        $taxAmount = $stripeCalculatedData['tax'];
        $shippingInclTax = round($shippingAmount + $taxAmount, 2);
        return [
            'shipping_amount' => $shippingAmount,
            'shipping_tax_amount' => $taxAmount,
            'shipping_incl_tax' => $shippingInclTax
        ];
    }

    private function calculateWithRuleOfThree($term1, $term2, $term3)
    {
        if ($term1 == 0) {
            return 0;
        }

        return round($term2 * $term3 / $term1, 2);
    }

    public function calculateItemDataBuyXGetYFree($price, $discountedPrice, $shipping, $fullQty, $discountQty, $taxBehaviour)
    {
        $stripeCalculatedData = $this->stripeTaxCalculator->calculateForPrice(
            $discountedPrice * $discountQty,
            $shipping,
            $this->taxRate,
            $taxBehaviour
        );
        if ($taxBehaviour == 'exclusive') {
            return $this->calculateExclusiveItemDataBuyXGetYFree($price, $fullQty, $discountQty, $stripeCalculatedData);
        } else {
            return $this->calculateInclusiveItemDataBuyXGetYFree($price, $fullQty, $discountQty, $stripeCalculatedData);
        }
    }

    private function calculateExclusiveItemDataBuyXGetYFree($price, $fullQty, $discountQty, $stripeCalculatedData)
    {
        $taxAmount = $stripeCalculatedData['tax'];
        $rowTotal = $price * $fullQty;
        $rowTaxBeforeDiscount = $this->calculateWithRuleOfThree($stripeCalculatedData['amount'], $rowTotal, $taxAmount);
        $taxBeforeDiscount = round($rowTaxBeforeDiscount / $fullQty, 2);
        $priceInclTax = $price + $taxBeforeDiscount;
        $rowTotalInclTax = $priceInclTax * $fullQty;

        return [
            'price' => $price,
            'row_total' => $rowTotal,
            'tax_amount' => $taxAmount,
            'price_incl_tax' => $priceInclTax,
            'row_total_incl_tax' => $rowTotalInclTax
        ];
    }

    private function calculateInclusiveItemDataBuyXGetYFree($price, $fullQty, $discountQty, $stripeCalculatedData)
    {
        $priceInclTax = $price;
        $rowTotalInclTax = $price * $fullQty;
        $taxAmount = $stripeCalculatedData['tax'];
        $rowTaxBeforeDiscount = $this->calculateWithRuleOfThree($stripeCalculatedData['amount'], $rowTotalInclTax, $stripeCalculatedData['tax']);
        $rowTotal = round($rowTotalInclTax - $rowTaxBeforeDiscount, 2);
        $price = round($rowTotal / $fullQty, 2);
        $discountCompensationAmount = round($rowTaxBeforeDiscount - $taxAmount, 2);

        return [
            'price' => $price,
            'row_total' => $rowTotal,
            'tax_amount' => $taxAmount,
            'price_incl_tax' => $priceInclTax,
            'row_total_incl_tax' => $rowTotalInclTax,
            'discount_tax_compensation_amount' => $discountCompensationAmount
        ];
    }
}