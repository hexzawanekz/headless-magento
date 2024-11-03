<?php

namespace StripeIntegration\Tax\Helper;

use StripeIntegration\Tax\Model\Adminhtml\Source\ShippingTaxBehavior;

class Tax
{
    public const TAX_BEHAVIOR_INCLUSIVE = 'inclusive';
    public const TAX_BEHAVIOR_EXCLUSIVE = 'exclusive';
    public const TAX_FREE_CODE = 'txcd_00000000';
    public const SHIPPING_TAX_CODE = 'txcd_92010001';

    private $configHelper;

    public function __construct(
        Config $configHelper
    )
    {
        $this->configHelper = $configHelper;
    }

    public function getProductAndPromotionTaxBehavior()
    {
        if ($this->isProductAndPromotionTaxExclusive()) {
            return self::TAX_BEHAVIOR_EXCLUSIVE;
        }

        return self::TAX_BEHAVIOR_INCLUSIVE;
    }

    public function getShippingTaxBehavior()
    {
        if ($this->isShippingTaxInclusive()) {
            return self::TAX_BEHAVIOR_INCLUSIVE;
        }

        return self::TAX_BEHAVIOR_EXCLUSIVE;
    }

    public function getShippingTaxCode()
    {
        if ($this->isShippingTaxFree()) {
            return self::TAX_FREE_CODE;
        } else {
            return self::SHIPPING_TAX_CODE;
        }
    }

    public function isShippingTaxFree()
    {
        return $this->configHelper->getConfigData('shipping_tax_behavior') === ShippingTaxBehavior::TAX_FREE_VALUE;
    }

    public function isShippingTaxInclusive()
    {
        return $this->configHelper->getConfigData('shipping_tax_behavior') === self::TAX_BEHAVIOR_INCLUSIVE;
    }

    public function isShippingTaxExclusive()
    {
        return $this->configHelper->getConfigData('shipping_tax_behavior') === self::TAX_BEHAVIOR_EXCLUSIVE;
    }

    public function isProductAndPromotionTaxInclusive()
    {
        return $this->configHelper->getConfigData('prices_and_promotions_tax_behavior') === self::TAX_BEHAVIOR_INCLUSIVE;
    }

    public function isProductAndPromotionTaxExclusive()
    {
        return $this->configHelper->getConfigData('prices_and_promotions_tax_behavior') === self::TAX_BEHAVIOR_EXCLUSIVE;
    }
}