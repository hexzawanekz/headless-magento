<?php

namespace StripeIntegration\Tax\Model\StripeTax;

use StripeIntegration\Tax\Helper\LineItems;
use StripeIntegration\Tax\Helper\Tax;
use Magento\Framework\Serialize\SerializerInterface;

class Response
{
    private $data;
    private $lineItemsHelper;
    private $serializer;

    public function __construct(
        LineItems $lineItemsHelper,
        SerializerInterface $serializer
    )
    {
        $this->lineItemsHelper = $lineItemsHelper;
        $this->serializer = $serializer;
    }

    public function setData($data)
    {
        if (is_string($data)) {
            $data = $this->serializer->unserialize($data);
        }

        $this->data = $data;

        return $this;
    }

    public function clear()
    {
        $this->data = [];
    }

    public function getLineItemData($id)
    {
        $lineItems = $this->data['line_items']['data'];
        $lineItem = $this->lineItemsHelper->getLineItemByReference($id, $lineItems);
        if ($lineItem) {
            $lineItemDetails = $this->getItemDetailsForCalculation($lineItem);
            $lineItemDetails['stripe_currency'] = strtoupper($this->data['currency']);
        } else {
            $lineItemDetails = $this->getDataForZeroTax($id);
        }

        return $lineItemDetails;
    }

    public function getShippingCostData()
    {
        $shippingCost = $this->data['shipping_cost'];
        $shippingCostDetails = $this->getItemDetailsForCalculation($shippingCost);
        $shippingCostDetails['stripe_currency'] = strtoupper($this->data['currency']);

        return $shippingCostDetails;
    }

    public function getDataForZeroTax($id = null)
    {
        return [
            'stripe_total_calculated_amount' => $id ? $this->data['prices'][$id] : $this->data['shipping'],
            'stripe_total_calculated_tax' => 0,
            'stripe_applied_taxes' => [],
            'stripe_currency' => $this->data['currency']
        ];
    }

    public function getTaxCalculationId()
    {
        return $this->data['id'];
    }

    private function getItemDetailsForCalculation($lineItem)
    {
        return [
            'stripe_total_calculated_amount' => $lineItem['amount'],
            'stripe_total_calculated_tax' => $lineItem['amount_tax'],
            'price_includes_tax' => $lineItem['tax_behavior'] == Tax::TAX_BEHAVIOR_INCLUSIVE,
            'stripe_applied_taxes' => $this->getAppliedTaxes($lineItem['tax_breakdown']),
        ];
    }

    private function getAppliedTaxes($taxBreakdown)
    {
        $appliedTaxes = [];

        foreach ($taxBreakdown as $taxItem) {
            if ($taxItem['amount'] == 0) {
                continue;
            }
            $taxArray = [
                'code' => $this->getTaxCode($taxItem),
                'percent' => (float)$taxItem['tax_rate_details']['percentage_decimal'],
                'title' => $this->getTaxTitle($taxItem),
                'amount' => $taxItem['amount']
            ];
            $appliedTaxes[$taxItem['jurisdiction']['level']][] = $taxArray;
        }

        return $appliedTaxes;
    }

    private function getTaxCode($taxItem)
    {
        $code = sprintf('%s - %s - ', $taxItem['jurisdiction']['country'], $taxItem['jurisdiction']['level']);
        if (($taxItem['jurisdiction']['state']) !== null) {
            $code .= $taxItem['jurisdiction']['state'] . ' - ';
        }
        $code .= $taxItem['jurisdiction']['display_name'];

        return $code;
    }

    private function getTaxTitle($taxItem)
    {
        return sprintf('%s - %s', $taxItem['jurisdiction']['display_name'], $taxItem['tax_rate_details']['display_name']);
    }
}