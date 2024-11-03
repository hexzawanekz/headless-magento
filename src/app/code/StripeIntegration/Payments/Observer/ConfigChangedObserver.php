<?php

namespace StripeIntegration\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;
use StripeIntegration\Payments\Exception\SilentException;
use StripeIntegration\Payments\Exception\GenericException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class ConfigChangedObserver implements ObserverInterface
{
    private $webhooksSetupFactory;
    private $helperFactory;
    private $configWriter;
    private $storeManager;
    private $request;
    private $scopeConfig;
    private $configFactory;
    private $config;

    public function __construct(
        \StripeIntegration\Payments\Helper\WebhooksSetupFactory $webhooksSetupFactory,
        \StripeIntegration\Payments\Helper\GenericFactory $helperFactory,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \StripeIntegration\Payments\Model\ConfigFactory $configFactory
    )
    {
        $this->webhooksSetupFactory = $webhooksSetupFactory;
        $this->helperFactory = $helperFactory;
        $this->configWriter = $configWriter;
        $this->storeManager = $storeManager;
        $this->request = $request;
        $this->scopeConfig = $scopeConfig;
        $this->configFactory = $configFactory;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $this->reconfigureWebhooks();
        $this->resetPaymentMethodConfiguration($observer->getChangedPaths());
    }

    private function getScopeAndId()
    {
        // Determine the scope and scope ID based on the request parameters
        if ($storeCode = $this->request->getParam('store')) {
            $scope = ScopeInterface::SCOPE_STORES;
            $scopeId = $this->storeManager->getStore($storeCode)->getId();
        } elseif ($websiteCode = $this->request->getParam('website')) {
            $scope = ScopeInterface::SCOPE_WEBSITES;
            $scopeId = $this->storeManager->getWebsite($websiteCode)->getId();
        } else {
            $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
            $scopeId = 0;
        }

        return [$scope, $scopeId];
    }

    public function reconfigureWebhooks()
    {
        // We use factories because this method is called from inside the Magento install scripts
        try
        {
            $webhooksSetup = $this->webhooksSetupFactory->create();
            $helper = $this->helperFactory->create();

            if ($webhooksSetup->isConfigureNeeded())
            {
                $webhooksSetup->configure();

                if (count($webhooksSetup->errorMessages) > 0)
                    $helper->addError("Errors encountered during Stripe webhooks configuration. Please see var/log/stripe_payments_webhooks.log for details.");
                else
                    $helper->addSuccess("Stripe webhooks have been re-configured successfully.");
            }
        }
        catch (SilentException $e)
        {
            if (!empty($helper) && $helper->isAdmin())
                $helper->addError($e->getMessage());
        }
        catch (\Exception $e)
        {
            // During the Magento installation, we may crash because the helper cannot be instantiated
        }
    }

    public function resetPaymentMethodConfiguration($changedPaths)
    {
        list($scope, $scopeId) = $this->getScopeAndId();

        // Define the fields to check for changes
        $fieldsToCheck = [
            'payment/stripe_payments_basic/stripe_mode',
            'payment/stripe_payments_basic/stripe_test_pk',
            'payment/stripe_payments_basic/stripe_live_pk',
            'payment/stripe_payments/payments/payment_method_configuration',
        ];

        $isChanged = false;

        foreach ($fieldsToCheck as $field)
        {
            if (!in_array($field, $changedPaths))
                continue;

            $isChanged = true;
        }

        // If any field has changed, reset the payment method configuration
        if ($isChanged) {
            $currentValue = $this->scopeConfig->getValue(
                'payment/stripe_payments/payments/payment_method_configuration',
                $scope,
                $scopeId
            );

            try
            {
                $config = $this->getStripeConfig();
                $config->initStripe();
                $paymentMethodConfiguration = $config->getStripeClient()->paymentMethodConfigurations->retrieve($currentValue);

                if (!$paymentMethodConfiguration->active)
                    throw new GenericException("The payment method configuration is no longer active.");
            }
            catch (\Exception $e)
            {
                $this->configWriter->delete(
                    'payment/stripe_payments/payments/payment_method_configuration',
                    $scope,
                    $scopeId
                );
            }
        }
    }

    private function getStripeConfig()
    {
        if (empty($this->config))
            $this->config = $this->configFactory->create();

        return $this->config;
    }
}
