<?php

namespace StripeIntegration\Payments\Helper;

use Magento\Store\Model\ScopeInterface;

class Config
{
    private $scopeConfig;
    private $resourceConfig;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Config\Model\ResourceModel\Config $resourceConfig
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->resourceConfig = $resourceConfig;
    }

    public function getConfigData($path, $storeId)
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function setConfigData($path, $value, $scope, $storeId)
    {
        return $this->resourceConfig->saveConfig($path, $value, $scope, $storeId);
    }
}