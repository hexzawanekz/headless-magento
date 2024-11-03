<?php

namespace StripeIntegration\Payments\Model;

use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Exception\LocalizedException;
use StripeIntegration\Payments\Exception\GenericException;

class Config
{
    public static $moduleName           = "Magento2";
    public static $moduleVersion        = "4.1.6";
    public static $minStripePHPVersion  = "13.15.0";
    public static $moduleUrl            = "https://stripe.com/docs/plugins/magento";
    public static $partnerId            = "pp_partner_Fs67gT2M6v3mH7";
    public const STRIPE_API             = "2020-03-02";
    public const BETAS_SERVER           = [];
    public const BETAS_CLIENT           = [];

    // State
    private $isInitialized;
    public $isSubscriptionsEnabled      = null;
    private $stripeClient         = null;

    // Constructor properties
    private $encryptor;
    private $logger;
    private $storeManager;
    private $stripeCustomerCollection;
    private $taxConfig;
    private $webhookCollection;
    private $storeRepository;
    private $productMetadata;
    private $cacheTypeList;
    private $appState;
    private $isStripeAPIKeyError;
    private $resourceConfig;
    private $scopeConfig;
    private $quoteHelper;
    private $accountCollectionFactory;
    private $accountFactory;
    private $configHelper;
    private $loggerHelper;
    private $orderHelper;
    private $areaCodeHelper;
    private $storeHelper;
    private $convert;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \StripeIntegration\Payments\Helper\Quote $quoteHelper,
        \StripeIntegration\Payments\Helper\Config $configHelper,
        \StripeIntegration\Payments\Helper\Logger $loggerHelper,
        \StripeIntegration\Payments\Helper\Convert $convert,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Config\Model\ResourceModel\Config $resourceConfig,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \StripeIntegration\Payments\Model\ResourceModel\StripeCustomer\Collection $stripeCustomerCollection,
        \Magento\Tax\Model\Config $taxConfig,
        \StripeIntegration\Payments\Model\ResourceModel\Webhook\Collection $webhookCollection,
        \Magento\Store\Api\StoreRepositoryInterface $storeRepository,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\App\State $appState,
        \StripeIntegration\Payments\Model\ResourceModel\Account\CollectionFactory $accountCollectionFactory,
        \StripeIntegration\Payments\Model\AccountFactory $accountFactory,
        \StripeIntegration\Payments\Helper\Order $orderHelper,
        \StripeIntegration\Payments\Helper\AreaCode $areaCodeHelper,
        \StripeIntegration\Payments\Helper\Store $storeHelper
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->loggerHelper = $loggerHelper;
        $this->quoteHelper = $quoteHelper;
        $this->configHelper = $configHelper;
        $this->convert = $convert;
        $this->encryptor = $encryptor;
        $this->resourceConfig = $resourceConfig;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->stripeCustomerCollection = $stripeCustomerCollection;
        $this->taxConfig = $taxConfig;
        $this->webhookCollection = $webhookCollection;
        $this->storeRepository = $storeRepository;
        $this->productMetadata = $productMetadata;
        $this->cacheTypeList = $cacheTypeList;
        $this->appState = $appState;
        $this->accountCollectionFactory = $accountCollectionFactory;
        $this->accountFactory = $accountFactory;
        $this->orderHelper = $orderHelper;
        $this->areaCodeHelper = $areaCodeHelper;
        $this->storeHelper = $storeHelper;

        $this->initStripe();
    }

    public function getComposerRequireVersion()
    {
        $version = explode(".", \StripeIntegration\Payments\Model\Config::$minStripePHPVersion);
        array_pop($version);
        return implode(".", $version);
    }

    public function canInitialize(&$error = null)
    {
        if (!class_exists('Stripe\Stripe'))
        {
            $error = "The Stripe PHP library dependency has not been installed. Please follow the installation instructions at https://stripe.com/docs/plugins/magento/install#manual";
            $this->logger->critical($error);
            return false;
        }

        if (version_compare(\Stripe\Stripe::VERSION, \StripeIntegration\Payments\Model\Config::$minStripePHPVersion) < 0)
        {
            $version = \StripeIntegration\Payments\Model\Config::$moduleVersion;
            $libVersion = $this->getComposerRequireVersion();
            $error = "Stripe Payments v$version depends on Stripe PHP library v$libVersion or newer. Please upgrade your installed Stripe PHP library with the command: composer require stripe/stripe-php:^$libVersion";
            $this->logger->critical($error);
            return false;
        }

        return true;
    }

    public function isInitialized()
    {
        if (!isset($this->isInitialized))
            return false;

        return $this->isInitialized;
    }

    public function initStripe($mode = null, $storeId = null)
    {
        if ($this->isInitialized())
            return true;

        if (!$this->canInitialize())
            return false;

        if ($this->getSecretKey($mode, $storeId) && $this->getPublishableKey($mode, $storeId))
        {
            $key = $this->getSecretKey($mode, $storeId);
            return $this->initStripeFromSecretKey($key);
        }

        return false;
    }

    public function getStripeAPIVersion()
    {
        $api = \StripeIntegration\Payments\Model\Config::STRIPE_API;

        if (!empty(\StripeIntegration\Payments\Model\Config::BETAS_SERVER))
        {
            $api .= "; " . implode("; ", \StripeIntegration\Payments\Model\Config::BETAS_SERVER);
        }

        return $api;
    }

    public function getPaymentMethodConfiguration()
    {
        $storeId = $this->storeHelper->getStoreId();
        $pmc = $this->scopeConfig->getValue("payment/stripe_payments/payments/payment_method_configuration", ScopeInterface::SCOPE_STORE, $storeId);
        if (empty($pmc))
            return null;

        try
        {
            $paymentMethodConfiguration = $this->getStripeClient()->paymentMethodConfigurations->retrieve($pmc);
            if (!$paymentMethodConfiguration->active)
                return null;
            else
                return $pmc;
        }
        catch (\Exception $e)
        {
            return null;
        }
    }

    public function reInitStripeFromSecretKey($key)
    {
        unset($this->isInitialized);
        return $this->initStripeFromSecretKey($key);
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
            $this->setAppInfo();
            \Stripe\Stripe::setApiKey($key);

            $api = $this->getStripeAPIVersion();

            \Stripe\Stripe::setApiVersion($api);
            $this->stripeClient = new \Stripe\StripeClient([
                "api_key" => $key,
                "stripe_version" => $api
            ]);

            $accountModel = $this->getAccountModel($key);
            if (!$accountModel->isValid())
            {
                $this->loggerHelper->logError("Invalid secret Stripe API keys.");
                return $this->isInitialized = false;
            }
        }
        catch (\Exception $e)
        {
            $this->loggerHelper->logError($e->getMessage(), $e->getTraceAsString());
            return $this->isInitialized = false;
        }

        return $this->isInitialized = true;
    }

    public function setAppInfo()
    {
        if ($this->canInitialize())
        {
            $appInfo = $this->getAppInfo();
            \Stripe\Stripe::setAppInfo($appInfo['name'], $appInfo['version'], $appInfo['url'], $appInfo['partner_id']);
        }
    }

    public function getAppInfo($clientSide = false)
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
            "name" => $this::$moduleName,
            "version" => ($clientSide ? $this::$moduleVersion : "{$this::$moduleVersion}_{$magentoVersion}_{$magentoEdition}"),
            "url" => $this::$moduleUrl,
            "partner_id" => $this::$partnerId
        ];
    }

    protected function initStripeFromPublicKey($key)
    {
        $secretKey = null;
        $stores = $this->storeManager->getStores();
        $configurations = [];

        foreach ($stores as $storeId => $store)
        {
            $testKeys = $this->getStoreViewAPIKey($store, 'test');
            if (!empty($testKeys['api_keys']['pk']) && $testKeys['api_keys']['pk'] == $key)
            {
                $secretKey = $testKeys['api_keys']['sk'];
                break;
            }

            $liveKeys = $this->getStoreViewAPIKey($store, 'live');
            if (!empty($liveKeys['api_keys']['pk']) && $liveKeys['api_keys']['pk'] == $key)
            {
                $secretKey = $liveKeys['api_keys']['sk'];
                break;
            }
        }

        return $this->initStripeFromSecretKey($secretKey);
    }

    public function reInitStripe($storeId, $currencyCode, $mode)
    {
        unset($this->isInitialized);
        $this->storeManager->setCurrentStore($storeId);
        $this->storeManager->getStore()->setCurrentCurrencyCode($currencyCode);
        return $this->initStripe($mode);
    }

    public function reInitStripeFromCustomerId($customerId)
    {
        $customer = $this->stripeCustomerCollection->getByStripeCustomerId($customerId);
        if (!$customer)
            return false;

        if (!$customer->getPk())
            return false;

        unset($this->isInitialized);
        return $this->initStripeFromPublicKey($customer->getPk());
    }

    public function reInitStripeFromStoreCode($storeCode, $mode = null)
    {
        $store = $this->storeRepository->getActiveStoreByCode($storeCode);
        if (!$store->getId())
            throw new GenericException("Could not find a store with code '$storeCode'");

        $storeId = $store->getStoreId();
        unset($this->isInitialized);
        $this->storeManager->setCurrentStore($storeId);

        if (!$mode)
            $mode = $this->getStripeMode($storeId);

        return $this->initStripe($mode, $storeId);
    }

    public function reInitStripeFromStoreId($storeId, $mode = null)
    {
        $store = $this->storeRepository->getActiveStoreById($storeId);
        if (!$store->getId())
            throw new GenericException("Could not find a store with id '$storeId'");

        unset($this->isInitialized);
        $this->storeManager->setCurrentStore($storeId);

        if (!$mode)
            $mode = $this->getStripeMode($storeId);

        return $this->initStripe($mode, $storeId);
    }

    public function getConfigData($field, $method = null, $storeId = null)
    {
        if (empty($storeId))
            $storeId = $this->storeHelper->getStoreId();

        $section = "";
        if ($method)
            $section = "_$method";

        return $this->configHelper->getConfigData("payment/stripe_payments$section/$field", $storeId);
    }

    public function getValue($configPath, $scope, $storeId = null)
    {
        return $this->scopeConfig->getValue($configPath, $scope, $storeId);
    }

    public function setConfigData($field, $value, $method = null, $scope = null, $storeId = null)
    {
        if (empty($storeId))
            $storeId = $this->storeHelper->getStoreId();

        if (empty($scope))
            $scope = ScopeInterface::SCOPE_STORE;

        $section = "";
        if ($method)
            $section = "_$method";

        return $this->configHelper->setConfigData("payment/stripe_payments$section/$field", $value, $scope, $storeId);
    }

    public function isSubscriptionsEnabled($storeId = null)
    {
        if ($this->isSubscriptionsEnabled !== null)
            return $this->isSubscriptionsEnabled;

        $this->isSubscriptionsEnabled = ((bool)$this->getConfigData('active', 'subscriptions', $storeId)) && $this->initStripe();
        return $this->isSubscriptionsEnabled;
    }

    public function isLevel3DataEnabled()
    {
        return (bool)$this->getConfigData("level3_data");
    }

    public function isEnabled()
    {
        return ((bool)$this->getConfigData('active') && $this->initStripe());
    }

    public function isReceiptEmailsEnabled()
    {
        return ((bool)$this->getConfigData('receipt_emails'));
    }

    public function getStripeMode($storeId = null)
    {
        return $this->getConfigData('stripe_mode', 'basic', $storeId);
    }

    public function getSecretKey($mode = null, $storeId = null)
    {
        if (empty($mode))
            $mode = $this->getStripeMode($storeId);

        $key = $this->getConfigData("stripe_{$mode}_sk", "basic", $storeId);

        return $this->decrypt($key);
    }

    public function decrypt($key)
    {
        if (empty($key))
            return null;

        if (!preg_match('/^[A-Za-z0-9_]+$/', $key))
            $key = $this->encryptor->decrypt($key);

        if (empty($key))
            return null;

        return trim($key);
    }

    public function getPublishableKey($mode = null, $storeId = null)
    {
        if (empty($mode))
            $mode = $this->getStripeMode();

        $pk = $this->getConfigData("stripe_{$mode}_pk", "basic", $storeId);

        if (empty($pk))
            return null;

        return trim($pk);
    }

    public function getCaptureMethod()
    {
        if ($this->isAuthorizeOnly())
            return "manual";

        return "automatic";
    }

    public function getWebhooksSigningSecrets()
    {
        $enabled = $this->scopeConfig->getValue("payment/stripe_payments/webhook_origin_check");
        if (!$enabled)
            return [];

        $secrets = [];
        $webhooks = $this->webhookCollection->getAllWebhooks();
        foreach ($webhooks as $webhook)
        {
            $secret = $webhook->getSecret();
            if (!empty($secret))
                $secrets[] = $secret;
        }

        return $secrets;
    }

    public function isAutomaticInvoicingEnabled()
    {
        return (bool)$this->getConfigData("automatic_invoicing");
    }

    // If the module is unconfigured, payment_action will be null, defaulting to authorize & capture
    // so this would still return the correct value
    public function isAuthorizeOnly()
    {
        return (
            $this->getPaymentAction() == "authorize"
            && !$this->quoteHelper->hasSubscriptions()
        );
    }

    public function getPaymentAction()
    {
        return $this->getConfigData('payment_action');
    }

    public function isStripeRadarEnabled()
    {
        return ($this->getConfigData('radar_risk_level') > 0);
    }

    public function getSavePaymentMethod()
    {
        return $this->getConfigData('save_payment_method');
    }

    public function getStatementDescriptor()
    {
        return substr((string)$this->getConfigData('statement_descriptor'), 0, 22);
    }

    public function retryWithSavedCard()
    {
        return $this->getConfigData('expired_authorizations') == 1;
    }

    public function displayCardIcons(): ?string
    {
        return $this->getConfigData("card_icons");
    }

    public function getCardIcons(): string
    {
        return (string)$this->getConfigData("card_icons_specific");
    }

    public function setIsStripeAPIKeyError($isError)
    {
        $this->isStripeAPIKeyError = $isError;
    }

    public function alwaysSaveCards()
    {
        return ($this->getSavePaymentMethod() ||
            $this->quoteHelper->hasSubscriptions() ||
            ($this->isAuthorizeOnly() && $this->retryWithSavedCard()) ||
            $this->quoteHelper->isMultiShipping() ||
            $this->getPaymentAction() == "order");
    }

    public function getIsStripeAPIKeyError()
    {
        if (isset($this->isStripeAPIKeyError))
            return $this->isStripeAPIKeyError;

        return false;
    }

    public function isRedirectPaymentFlow($storeId = null)
    {
        return ($this->getConfigData('payment_flow', null, $storeId) == 1);
    }

    // Overwrite this based on business needs
    public function getMetadata($order)
    {
        $metadata = [
            "Module" => $this->module(),
            "Order #" => $order->getIncrementId()
        ];

        if ($order->getCustomerIsGuest())
            $metadata["Guest"] = "Yes";

        return $metadata;
    }

    public function getMultishippingMetadata($quote, $orders)
    {
        $orderIncrementIds = [];
        foreach ($orders as $order)
            $orderIncrementIds[] = $order->getIncrementId();

        $orders = implode(',', $orderIncrementIds);

        if (strlen($orders) > 500)
            throw new LocalizedException(__("Too many orders, please reduce shipping addresses and try again."));

        $metadata = [
            "Module" => $this->module(),
            "Cart #" => $quote->getId(),
            "Orders" => $orders,
            "Multishipping" => "Yes"
        ];

        if ($quote->getCustomerIsGuest())
            $metadata["Guest"] = "Yes";

        return $metadata;
    }

    private function module()
    {
        return Config::$moduleName . " v" . Config::$moduleVersion;
    }

    public function getStripeParamsFrom($order)
    {
        $amount = $order->getGrandTotal();
        $currency = $order->getOrderCurrencyCode();

        $params = [
            "amount" => $this->convert->magentoAmountToStripeAmount($amount, $currency),
            "currency" => $currency,
            "description" => $this->orderHelper->getOrderDescription($order),
            "metadata" => $this->getMetadata($order)
        ];

        $customerEmail = $order->getCustomerEmail();
        if ($customerEmail && $this->isReceiptEmailsEnabled())
            $params["receipt_email"] = $customerEmail;

        return $params;
    }

    public function getStoreViewAPIKey($store, $mode)
    {
        $secretKey = $this->scopeConfig->getValue("payment/stripe_payments_basic/stripe_{$mode}_sk", \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store['code']);
        if (empty($secretKey))
            return null;

        return array_merge($store->getData(), [
            'api_keys' => [
                'pk' => $this->scopeConfig->getValue("payment/stripe_payments_basic/stripe_{$mode}_pk", \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store['code']),
                'sk' => $this->decrypt($secretKey)
            ],
            'mode' => $mode,
            'mode_label' => ucfirst($mode) . " Mode",
            'default_currency' => $store->getDefaultCurrency()->getCurrencyCode()
        ]);
    }

    public function getStripeClient()
    {
        return $this->stripeClient;
    }

    public function isStripeTaxEnabled($storeId = null)
    {
        return (bool)$this->configHelper->getConfigData('tax/stripe_tax/enabled', $storeId);
    }

    public function getStripeTaxShippingBehavior($storeId = null)
    {
        return $this->configHelper->getConfigData('tax/stripe_tax/shipping_tax_behavior', $storeId);
    }

    public function getStripeTaxPricesAndPromotionsBehavior($storeId = null)
    {
        return $this->configHelper->getConfigData('tax/stripe_tax/prices_and_promotions_tax_behavior', $storeId);
    }

    public function shippingIncludesTax($storeId = null)
    {
        if ($this->isStripeTaxEnabled($storeId))
        {
            return $this->getStripeTaxShippingBehavior($storeId) == "inclusive";
        }

        return $this->taxConfig->shippingPriceIncludesTax($storeId);
    }

    public function priceIncludesTax($storeId = null)
    {
        if ($this->isStripeTaxEnabled($storeId))
        {
            return $this->getStripeTaxPricesAndPromotionsBehavior($storeId) == "inclusive";
        }

        return $this->taxConfig->priceIncludesTax($storeId);
    }

    public function getSetupFutureUsage($quote)
    {
        if ($this->areaCodeHelper->isAdmin())
            return null;

        if ($this->quoteHelper->hasSubscriptions($quote))
            return "off_session";

        if ($this->isAuthorizeOnly() && $this->retryWithSavedCard())
            return "on_session";

        if ($this->quoteHelper->isMultiShipping($quote))
            return "on_session";

        if ($this->getSavePaymentMethod())
            return "on_session";

        return null;
    }

    public function enableOriginCheck()
    {
        $this->resourceConfig->saveConfig("payment/stripe_payments/webhook_origin_check", "1", "default", 0);
    }

    public function disableOriginCheck()
    {
        $this->resourceConfig->saveConfig("payment/stripe_payments/webhook_origin_check", "0", "default", 0);
    }

    public function clearCache($type)
    {
        $this->cacheTypeList->cleanType($type);
    }

    public function getMagentoMode()
    {
        return $this->appState->getMode();
    }

    public function getAllAPIKeys()
    {
        $keys = [];
        $stores = $this->storeManager->getStores();

        foreach ($stores as $store)
        {
            foreach (['live', 'test'] as $mode)
            {
                $sk = $this->scopeConfig->getValue("payment/stripe_payments_basic/stripe_{$mode}_sk", \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store->getCode());
                $sk = (empty($sk) ? null : $this->decrypt($sk) );
                $pk = $this->scopeConfig->getValue("payment/stripe_payments_basic/stripe_{$mode}_sk", \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store->getCode());
                $pk = (empty($pk) ? null : $this->decrypt($pk) );

                if (!empty($sk) && !empty($pk))
                {
                    $keys[$sk] = $pk;
                }
            }
        }

        return $keys;
    }

    public function reCheckCVCForSavedCards()
    {
        $config = $this->getConfigData("cvc_code");

        return ($config == "new_saved_cards");
    }

    public function isVerticalLayout()
    {
        if ($this->isMobile())
        {
            return true;
        }

        return $this->getConfigData('payment_element_layout');
    }

    // Override this method if you'd like to always use the vertical layout on mobile
    protected function isMobile()
    {
        // $userAgent = $this->httpHeader->getHttpUserAgent();

        // if(strpos($userAgent, 'Android') !== false ||
        //     strpos($userAgent, 'iPhone') !== false ||
        //     strpos($userAgent, 'iPad') !== false ||
        //     strpos($userAgent, 'iPod') !== false)
        // {
        //     return true;
        // }

        return false;
    }

    public function getAccountModel($secretKey = null)
    {
        $publishableKey = $this->getPublishableKey();
        if (empty($secretKey))
        {
            $secretKey = $this->getSecretKey();

            if (empty($publishableKey) || empty($secretKey))
            {
                return $this->accountFactory->create();
            }
            else
            {
                $hash = hash('sha256', $secretKey);
                $accountModel = $this->accountCollectionFactory->create()->findByKeys($publishableKey, $hash);
            }
        }
        else
        {
            $hash = hash('sha256', $secretKey);
            $accountModel = $this->accountFactory->create()->load($hash, 'secret_key');
        }

        if (!$accountModel->getId() || $accountModel->needsRefresh())
        {
            try
            {
                if (!$this->getStripeClient())
                {
                    return $accountModel;
                }

                $account = $this->getStripeClient()->accounts->retrieve();
                $accountModel->fromStripeObject($account);
                $accountModel->setPublishableKey($publishableKey);
                $accountModel->setSecretKey($hash);
                $accountModel->setIsValid(true);
                $accountModel->setUpdatedAt(date('Y-m-d H:i:s'));
                $accountModel->save();
            }
            catch (\Exception $e)
            {
                $accountModel->setPublishableKey($publishableKey);
                $accountModel->setSecretKey($hash);
                $accountModel->setIsValid(false);
                $accountModel->save();
                throw $e;
            }
        }

        return $accountModel;
    }

    public function getECEMode($viewingSubscriptionProduct = false)
    {
        if ($this->isSubscriptionsEnabled())
        {
            if ($viewingSubscriptionProduct)
            {
                return "subscription";
            }
            else if ($this->quoteHelper->hasSubscriptions())
            {
                // Regular products may also exist in this cart. We still go for subscribe mode.
                return "subscription";
            }
        }

        if ($this->getPaymentAction() == "order")
        {
            return "setup";
        }

        return "payment";
    }

    public function getManualAuthenticationPaymentMethods(): array
    {
        $methods = [];
        $config = $this->configHelper->getConfigData("stripe_settings/manual_authentication", 0);

        if ($this->areaCodeHelper->isGraphQLRequest())
        {
            $methods = explode(",", (string)$config['graphql_api']);
        }
        else
        {
            $methods = explode(",", (string)$config['rest_api']);
        }

        return $methods;
    }
}
