<?php

namespace StripeIntegration\Tax\Helper;

use Magento\Store\Model\StoreManagerInterface;

class Request
{
    private $storeHelper;

    public function __construct(
        Store $storeHelper
    ) {
        $this->storeHelper = $storeHelper;
    }

    public function getCurrency()
    {
        return $this->storeHelper->getCurrentStore()->getCurrentCurrencyCode();
    }
}