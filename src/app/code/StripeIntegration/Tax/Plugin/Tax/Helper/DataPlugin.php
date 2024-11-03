<?php

namespace StripeIntegration\Tax\Plugin\Tax\Helper;

use Magento\Tax\Helper\Data;
use StripeIntegration\Tax\Model\StripeTax;

class DataPlugin
{
    private $stripeTax;

    public function __construct(
        StripeTax $stripeTax
    )
    {
        $this->stripeTax = $stripeTax;
    }

    public function afterApplyTaxOnCustomPrice(
        Data $subject,
        $result
    )
    {
        if ($this->stripeTax->isEnabled()) {
            return true;
        }

        return $result;
    }

    public function afterApplyTaxOnOriginalPrice(
        Data $subject,
        $result
    )
    {
        if ($this->stripeTax->isEnabled()) {
            return false;
        }

        return $result;
    }
}