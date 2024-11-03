<?php

namespace StripeIntegration\Tax\Plugin\Tax\Model\Sales\Total\Quote;

use Magento\GiftWrapping\Model\Total\Quote\Tax\Giftwrapping;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Tax\Api\Data\QuoteDetailsItemInterfaceFactory;
use Magento\Tax\Model\Sales\Total\Quote\Tax;
use StripeIntegration\Tax\Helper\GiftOptions;
use StripeIntegration\Tax\Helper\Logger;
use StripeIntegration\Tax\Model\StripeTax;
use Magento\Quote\Model\Quote\Address\Total;
use StripeIntegration\Tax\Model\TaxFlow;

class TaxPlugin
{
    private $stripeTax;
    private $taxHelper;
    private $lineItemsHelper;
    private $giftOptionsHelper;
    private $taxFlow;
    private $logger;

    public function __construct(
        StripeTax $stripeTax,
        \StripeIntegration\Tax\Helper\Tax $taxHelper,
        \StripeIntegration\Tax\Helper\LineItems $lineItemsHelper,
        GiftOptions $giftOptionsHelper,
        TaxFlow $taxFlow,
        Logger $logger
    )
    {
        $this->stripeTax = $stripeTax;
        $this->taxHelper = $taxHelper;
        $this->lineItemsHelper = $lineItemsHelper;
        $this->giftOptionsHelper = $giftOptionsHelper;
        $this->taxFlow = $taxFlow;
        $this->logger = $logger;
    }
    public function beforeCollect(
        Tax $subject,
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Quote\Address\Total $total
    )
    {
        if (!$this->stripeTax->isEnabled() || !$shippingAssignment->getItems()) {
            return null;
        }

        $this->stripeTax->calculate($quote, $shippingAssignment, $total);

        return null;
    }

    public function afterCollect(
        Tax $subject,
        $result,
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Quote\Address\Total $total
    )
    {
        if ($this->stripeTax->isEnabled() && $this->stripeTax->hasValidResponse()) {
            $total->setData('stripe_tax_calculation_id', $this->stripeTax->getResponse()->getTaxCalculationId());
        }

        return $result;
    }

    public function afterMapItem(
        Tax $subject,
        $result,
        QuoteDetailsItemInterfaceFactory $itemDataObjectFactory,
        Quote\Item\AbstractItem $item,
        $priceIncludesTax,
        $useBaseCurrency,
        $parentCode = null
    ) {
        if (!$this->stripeTax->isEnabled()) {
            return $result;
        }

        try {
            $response = $this->stripeTax->getResponse();
            $id = $this->lineItemsHelper->getReference($item);
            $lineItemData = $this->getLineItemData($id, $response);

            $newReturnData = $this->getNewResultData($result, $lineItemData, $useBaseCurrency);
            $result->setData($newReturnData);
        } catch (\Exception $e) {
            $this->logger->debug(
                'Issue encountered at item mapping step:' . PHP_EOL . $e->getMessage(),
                $e->getTraceAsString()
            );
            $this->taxFlow->orderMappingIssues = true;
        }


        return $result;
    }

    public function afterGetShippingDataObject(
        Tax $subject,
        $result,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total,
        $useBaseCurrency
    )
    {
        if (!$this->stripeTax->isEnabled()) {
            return $result;
        }

        try {
            $response = $this->stripeTax->getResponse();
            if ($this->stripeTax->hasValidResponse()) {
                $shippingData = $response->getShippingCostData();
            } else {
                $shippingData = $response->getDataForZeroTax();
            }

            $newReturnData = $this->getNewResultData($result, $shippingData, $useBaseCurrency);
            $result->setData($newReturnData);
        } catch (\Exception $e) {
            $this->logger->debug(
                'Issue encountered at shipping data step:' . PHP_EOL . $e->getMessage(),
                $e->getTraceAsString()
            );
            $this->taxFlow->orderMappingIssues = true;
        }

        return $result;
    }

    public function afterMapItemExtraTaxables(
        Tax $subject,
        $result,
        \Magento\Tax\Api\Data\QuoteDetailsItemInterfaceFactory $itemDataObjectFactory,
        AbstractItem $item,
        $priceIncludesTax,
        $useBaseCurrency
    )
    {
        if (!$this->stripeTax->isEnabled() || !$result) {
            return $result;
        }

        try {
            $response = $this->stripeTax->getResponse();

            // Go through all the extra items for tax calculation and if we find items corresponding to them
            // in the Stripe calculation response, we add the details to the taxable item
            foreach ($result as $extraTaxableItem) {
                // Only process the taxable item if the price for it is greater than 0
                if ($extraTaxableItem->getUnitPrice() > 0) {
                    $id = $this->lineItemsHelper->getItemAdditionalFeeReference($item, $extraTaxableItem->getType());
                    $lineItemData = $this->getLineItemData($id, $response);

                    if ($lineItemData) {
                        $newReturnData = $this->getNewResultData($extraTaxableItem, $lineItemData, $useBaseCurrency);
                        $extraTaxableItem->setData($newReturnData);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->debug(
                'Issue encountered at item additional fee step:' . PHP_EOL . $e->getMessage(),
                $e->getTraceAsString()
            );
            $this->taxFlow->orderMappingIssues = true;
        }


        return $result;
    }

    public function afterMapQuoteExtraTaxables(
        Tax $subject,
        $result,
        \Magento\Tax\Api\Data\QuoteDetailsItemInterfaceFactory $itemDataObjectFactory,
        Address $address,
        $useBaseCurrency
    )
    {
        if (!$this->stripeTax->isEnabled() || !$result) {
            return $result;
        }

        try {
            $response = $this->stripeTax->getResponse();

            // Go through all the extra items for tax calculation and if we find items corresponding to them
            // in the Stripe calculation response, we add the details to the taxable item
            foreach ($result as $quoteExtraTaxableItem) {
                // Only process the taxable item if the price for it is greater than 0
                if ($quoteExtraTaxableItem->getUnitPrice() > 0) {
                    $id = $this->lineItemsHelper->getSalesEntityAdditionalFeeReference($address->getQuote(), $quoteExtraTaxableItem->getType());
                    $lineItemData = $this->getLineItemData($id, $response);

                    if ($lineItemData) {
                        $newReturnData = $this->getNewResultData($quoteExtraTaxableItem, $lineItemData, $useBaseCurrency);
                        $quoteExtraTaxableItem->setData($newReturnData);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->debug(
                'Issue encountered at quote additional fees step:' . PHP_EOL . $e->getMessage(),
                $e->getTraceAsString()
            );
            $this->taxFlow->orderMappingIssues = true;
        }

        return $result;
    }

    private function getLineItemData($id, $response)
    {
        if ($this->stripeTax->hasValidResponse()) {
            $lineItemData = $response->getLineItemData($id);
        } else {
            $lineItemData = $response->getDataForZeroTax($id);
        }

        return $lineItemData;
    }

    private function getLineItemId($item)
    {
        if ($item->getItemId()) {
            return $item->getItemId();
        }

        return $item->getSku();
    }

    private function getNewResultData($result, $stripeData, $useBaseCurrency)
    {
        $currentReturnData = $result->getData();
        $newData = array_merge($currentReturnData, $stripeData);
        $newData['use_base_currency'] = $useBaseCurrency;
        $newData['is_stripe_prepared'] = true;

        return $newData;
    }
}