<?php

namespace StripeIntegration\Payments\Plugin;

use Magento\Framework\App\Action\AbstractAction;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreCookieManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

class Context
{
    private $session;
    private $httpContext;
    private $storeManager;
    private $storeCookieManager;
    private $helper;
    private $subscriptionsHelper;
    private $config;
    private $processed = false;

    /**
     * @param SessionManagerInterface $session
     * @param HttpContext $httpContext
     * @param StoreManagerInterface $storeManager
     * @param StoreCookieManagerInterface $storeCookieManager
     */
    public function __construct(
        SessionManagerInterface $session,
        HttpContext $httpContext,
        StoreManagerInterface $storeManager,
        StoreCookieManagerInterface $storeCookieManager,
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Helper\Subscriptions $subscriptionsHelper,
        \StripeIntegration\Payments\Model\Config $config
    ) {
        $this->session      = $session;
        $this->httpContext  = $httpContext;
        $this->storeManager = $storeManager;
        $this->storeCookieManager = $storeCookieManager;
        $this->helper = $helper;
        $this->subscriptionsHelper = $subscriptionsHelper;
        $this->config = $config;
    }

    /**
     * Set store and currency to http context.
     *
     * @param AbstractAction $subject
     * @param RequestInterface $request
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeDispatch(
        AbstractAction $subject,
        RequestInterface $request
    ) {
        if ($this->processed) {
            return;
        }
        $this->processed = true;

        /** @var string|array|null $storeCode */
        $storeCode = $request->getParam(
            StoreManagerInterface::PARAM_NAME,
            $this->storeCookieManager->getStoreCodeFromCookie()
        );
        if (is_array($storeCode)) {
            if (!isset($storeCode['_data']['code'])) {
                $this->processInvalidStoreRequested();
            }
            $storeCode = $storeCode['_data']['code'];
        }
        if ($storeCode === '') {
            //Empty code - is an invalid code and it was given explicitly
            //(the value would be null if the code wasn't found).
            $this->processInvalidStoreRequested();
        }
        try {
            $currentStore = $this->storeManager->getStore($storeCode);
            $this->updateContext($currentStore);
        } catch (NoSuchEntityException $exception) {
            $this->processInvalidStoreRequested($exception);
        }
    }

    /**
     * Update context accordingly to the store found.
     *
     * @param StoreInterface $store
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function updateContext(StoreInterface $store)
    {
        if (!$this->config->isSubscriptionsEnabled())
            return;

        if (!$this->subscriptionsHelper->hasSubscriptions())
            return;

        $customerModel = $this->helper->getCustomerModel();
        if (!$customerModel->getStripeId())
            return;

        $stripeCustomer = $customerModel->retrieveByStripeID();
        if (empty($stripeCustomer->currency))
            return;

        $newCurrency = strtoupper($stripeCustomer->currency);
        $currenctCurrency = $store->getCurrentCurrencyCode();

        if ($newCurrency != $currenctCurrency)
        {
            $availableCurrencyCodes = $store->getAvailableCurrencyCodes(true);

            if (!in_array($newCurrency, $availableCurrencyCodes))
                return;

            $store->setCurrentCurrencyCode($newCurrency);
            $this->session->setCurrencyCode($newCurrency);
            $this->httpContext->setValue(HttpContext::CONTEXT_CURRENCY, $newCurrency, $newCurrency);
        }
    }

    /**
     * Take action in case of invalid store requested.
     *
     * @param NoSuchEntityException|null $previousException
     * @return void
     * @throws NotFoundException
     */
    private function processInvalidStoreRequested(
        NoSuchEntityException $previousException = null
    ) {
        $store = $this->storeManager->getStore();
        $this->updateContext($store);

        throw new NotFoundException(
            $previousException
                ? __($previousException->getMessage())
                : __('Invalid store requested.'),
            $previousException
        );
    }
}
