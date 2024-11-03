<?php

namespace StripeIntegration\Payments\Model\Tax;

use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Magento\Tax\Model\Config;
use StripeIntegration\Payments\Model\InitialFee;

class InitialFeeTax extends AbstractTotal
{
    private $taxConfig;

    public function __construct(
        Config $taxConfig
    ) {
        $this->taxConfig = $taxConfig;
    }

    /**
     * Add initial fee and initial fee tax to the items
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param \Magento\Quote\Model\Quote\Address\Total $total
     * @return $this
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function collect(
        \Magento\Quote\Model\Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        \Magento\Quote\Model\Quote\Address\Total $total
    ) {
        $extraTaxableDetails = $total->getExtraTaxableDetails();
        $initialFeeItemsDetails = $this->getInitialFeeItemsDetails($extraTaxableDetails);
        if (!$initialFeeItemsDetails) {
            return $this;
        }

        $mappedItems = $total->getInitialFeeMappedItems();
        $priceIncludesTax = $this->taxConfig->priceIncludesTax();

        $totalTax = 0;
        $totalBaseTax = 0;

        foreach ($initialFeeItemsDetails as $initialFeeItemDetails) {
            foreach ($initialFeeItemDetails as $detail) {
                $mappedItemCode = $detail['code'];
                if (!array_key_exists($mappedItemCode, $mappedItems)) {
                    continue;
                }
                $item = $mappedItems[$mappedItemCode];

                if ($priceIncludesTax) {
                    $item->setInitialFee($detail['row_total_incl_tax']);
                    $item->setBaseInitialFee($detail['base_row_total_incl_tax']);
                } else {
                    $item->setInitialFee($detail['row_total_excl_tax']);
                    $item->setBaseInitialFee($detail['base_row_total_excl_tax']);
                }

                // Add the fee to the grand total after the taxes were calculated.
                // We do this because the value provided for initial fee is either including or excluding tax based on
                // the settings in the admin. After the tax is being calculated we have the price for the initial fee
                // including and excluding the tax. We will add the price excluding the tax to the grand total,
                // as the tax for it will be added as well automatically here:
                // vendor/magento/module-tax/Model/Sales/Total/Quote/Tax.php:303
                $total->addTotalAmount('initial_fee', $detail['row_total_excl_tax']);
                $total->addBaseTotalAmount('base_initial_fee', $detail['base_row_total_excl_tax']);

                $item->setInitialFeeTax($detail['row_tax']);
                $item->setBaseInitialFeeTax($detail['base_row_tax']);

                $totalTax += $detail['row_tax'];
                $totalBaseTax += $detail['base_row_tax'];
            }
        }

        $quote->setInitialFeeTax($totalTax);
        $quote->setBaseInitialFeeTax($totalBaseTax);

        return $this;
    }
    
    private function getInitialFeeItemsDetails($extraTaxableDetails)
    {
        return isset($extraTaxableDetails[InitialFee::INITIAL_FEE_TYPE]) ? $extraTaxableDetails[InitialFee::INITIAL_FEE_TYPE] : [];
    }

    public function fetch(\Magento\Quote\Model\Quote $quote, \Magento\Quote\Model\Quote\Address\Total $total)
    {
        return [
            'code' => 'initial_fee_tax',
            'title' => __('Initial Fee Tax'),
            'initial_fee_tax' => $total->getInitialFeeTax(),
            'base_initial_fee_tax' => $total->getBaseInitialFeeTax()
        ];
    }
}