<?php

namespace StripeIntegration\Tax\Model;

use Magento\Framework\App\ProductMetadataInterface;
use StripeIntegration\Tax\Helper\Logger;
use \StripeIntegration\Tax\Helper\Config as ConfigHelper;
use StripeIntegration\Tax\Exceptions\Exception;
use StripeIntegration\Tax\Helper\Store;

class Config
{
    public const MODULE_NAME            = "Magento2-Tax";
    public const MODULE_VERSION         = "1.0.1";
    private const MODULE_URL            = "https://docs.stripe.com/connectors/adobe-commerce/tax";
    private const PARTNER_ID            = "pp_partner_Fs67gT2M6v3mH7";
    private const STRIPE_API            = "2023-10-16";
    private const STATUS_ACTIVE         = 'active';
    private $isInitialized;
    private $stripeClient = null;
    private $configHelper;
    private $loggerHelper;
    private $productMetadata;
    private $storeHelper;

    public function __construct(
        ConfigHelper $configHelper,
        Logger $loggerHelper,
        ProductMetadataInterface $productMetadata,
        Store $storeHelper
    ) {
        $this->configHelper = $configHelper;
        $this->loggerHelper = $loggerHelper;
        $this->productMetadata = $productMetadata;
        $this->storeHelper = $storeHelper;

        $this->initStripe();
    }

    public function initStripe($mode = null, $storeId = null)
    {
        if ($this->isInitialized())
            return true;

        if (!$this->canInitialize())
            return false;

        if ($this->configHelper->getSecretKey($mode, $storeId)
            && $this->configHelper->getPublishableKey($mode, $storeId))
        {
            $key = $this->configHelper->getSecretKey($mode, $storeId);
            return $this->initStripeFromSecretKey($key);
        }

        return false;
    }

    public function isInitialized()
    {
        if (!isset($this->isInitialized))
            return false;

        return $this->isInitialized;
    }

    public function isEnabled()
    {
        return $this->configHelper->getIsEnabled() && $this->isInitialized();
    }

    private function canInitialize()
    {
        if (!class_exists('Stripe\Stripe'))
        {
            return false;
        }

        return true;
    }

    public function initStripeFromSecretKey($key)
    {
        if (!$this->canInitialize())
            return $this->isInitialized = false;

        if (empty($key))
            return $this->isInitialized = false;

        if (isset($this->isInitialized))
            return $this->isInitialized;

        try
        {
            $appInfo = $this->getAppInfo();

            $this->stripeClient = new \Stripe\StripeClient([
                "api_key" => $key,
                "stripe_version" => self::STRIPE_API,
                "app_info" => $appInfo
            ]);
        }
        catch (\Exception $e)
        {
            $this->loggerHelper->logError($e->getMessage(), $e->getTraceAsString());
            return $this->isInitialized = false;
        }

        return $this->isInitialized = true;
    }

    private function setAppInfo()
    {
        if ($this->canInitialize())
        {
            $appInfo = $this->getAppInfo();
            \Stripe\Stripe::setAppInfo($appInfo['name'], $appInfo['version'], $appInfo['url'], $appInfo['partner_id']);
        }
    }

    private function getAppInfo()
    {
        $magentoVersion = "unknown";
        $magentoEdition = "unknown";

        try
        {
            $magentoVersion = $this->productMetadata->getVersion();
            $magentoEdition = $this->productMetadata->getEdition();
        }
        catch (\Exception $e)
        {

        }

        return [
            "name" => self::MODULE_NAME,
            "version" => self::MODULE_VERSION . "_{$magentoVersion}_{$magentoEdition}",
            "url" => self::MODULE_URL ,
            "partner_id" => self::PARTNER_ID
        ];
    }

    public function getStripeClient()
    {
        return $this->stripeClient;
    }

    public function reInitStripeFromStore($store, $mode = null)
    {
        if (empty($store) || !$store->getStoreId())
            throw new Exception("Cannot re-initialize Stripe from an invalid store object.");

        unset($this->isInitialized);
        $this->storeHelper->setCurrentStore($store->getStoreId());

        if (!$mode)
            $mode = $this->configHelper->getStripeMode($store->getStoreId());

        return $this->initStripe($mode, $store->getStoreId());
    }
}