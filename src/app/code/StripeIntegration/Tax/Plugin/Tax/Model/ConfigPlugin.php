<?php

namespace StripeIntegration\Tax\Plugin\Tax\Model;

use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config;
use StripeIntegration\Tax\Helper\Tax;
use StripeIntegration\Tax\Model\StripeTax;

class ConfigPlugin
{
    private $stripeTax;
    private $taxHelper;

    public function __construct(
        StripeTax $stripeTax,
        Tax $taxHelper
    )
    {
        $this->stripeTax = $stripeTax;
        $this->taxHelper = $taxHelper;
    }

    public function afterGetAlgorithm(Config $subject, $result)
    {
        if (!$this->stripeTax->isEnabled()) {
            return $result;
        }

        return Calculation::CALC_ROW_BASE;
    }

    public function afterPriceIncludesTax(Config $subject, $result)
    {
        if (!$this->stripeTax->isEnabled()) {
            return $result;
        }

        if ($this->taxHelper->isProductAndPromotionTaxInclusive()) {
            return true;
        }

        return false;
    }

    public function afterShippingPriceIncludesTax(Config $subject, $result)
    {
        if (!$this->stripeTax->isEnabled()) {
            return $result;
        }

        if ($this->taxHelper->isShippingTaxInclusive()) {
            return true;
        }

        return false;
    }

    public function afterGetShippingTaxClass(Config $subject, $result)
    {
        if (!$this->stripeTax->isEnabled()) {
            return $result;
        }

        if ($this->taxHelper->isShippingTaxFree()) {
            return 0;
        }

        return $result;
    }

    public function afterCrossBorderTradeEnabled(Config $subject, $result)
    {
        if (!$this->stripeTax->isEnabled()) {
            return $result;
        }

        if ($this->taxHelper->isProductAndPromotionTaxInclusive()) {
            return 1;
        }

        return 0;
    }

    public function afterGetPriceDisplayType(Config $subject, $result)
    {
        if (!$this->stripeTax->isEnabled()) {
            return $result;
        }

        if ($this->taxHelper->isProductAndPromotionTaxInclusive()) {
            return Config::DISPLAY_TYPE_INCLUDING_TAX;
        }

        return Config::DISPLAY_TYPE_EXCLUDING_TAX;
    }

    public function afterGetShippingPriceDisplayType(Config $subject, $result)
    {
        if (!$this->stripeTax->isEnabled()) {
            return $result;
        }

        if ($this->taxHelper->isShippingTaxInclusive()) {
            return Config::DISPLAY_TYPE_INCLUDING_TAX;
        }

        return Config::DISPLAY_TYPE_EXCLUDING_TAX;
    }

    public function afterDiscountTax(Config $subject, $result)
    {
        if (!$this->stripeTax->isEnabled()) {
            return $result;
        }

        if ($this->taxHelper->isProductAndPromotionTaxInclusive()) {
            return true;
        }

        return false;
    }
}