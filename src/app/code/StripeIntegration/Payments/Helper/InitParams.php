<?php

namespace StripeIntegration\Payments\Helper;

class InitParams
{
    private $helper;
    private $paymentMethodHelper;
    private $paymentElement;
    private $expressCheckoutConfig;
    private $customer;
    private $localeHelper;
    private $config;
    private $serializer;
    private $quoteHelper;
    private $subscriptionProductFactory;
    private $paymentMethodTypesHelper;
    private $paymentMethodOptionsService;
    private $productHelper;

    public function __construct(
        \Magento\Framework\Serialize\SerializerInterface $serializer,
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Helper\Quote $quoteHelper,
        \StripeIntegration\Payments\Helper\Locale $localeHelper,
        \StripeIntegration\Payments\Helper\PaymentMethodTypes $paymentMethodTypesHelper,
        \StripeIntegration\Payments\Helper\Product $productHelper,
        \StripeIntegration\Payments\Model\ExpressCheckout\Config $expressCheckoutConfig,
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Model\PaymentElement $paymentElement,
        \StripeIntegration\Payments\Model\SubscriptionProductFactory $subscriptionProductFactory,
        \StripeIntegration\Payments\Helper\PaymentMethod $paymentMethodHelper,
        \StripeIntegration\Payments\Service\PaymentMethodOptionsService $paymentMethodOptionsService
    ) {
        $this->serializer = $serializer;
        $this->helper = $helper;
        $this->quoteHelper = $quoteHelper;
        $this->localeHelper = $localeHelper;
        $this->paymentMethodTypesHelper = $paymentMethodTypesHelper;
        $this->productHelper = $productHelper;
        $this->expressCheckoutConfig = $expressCheckoutConfig;
        $this->config = $config;
        $this->paymentElement = $paymentElement;
        $this->subscriptionProductFactory = $subscriptionProductFactory;
        $this->paymentMethodHelper = $paymentMethodHelper;
        $this->paymentMethodOptionsService = $paymentMethodOptionsService;
        $this->customer = $helper->getCustomerModel();
    }

    public function getCheckoutParams()
    {
        if ($this->helper->isMultiShipping()) // Called by the UIConfigProvider
        {
            return $this->getMultishippingParams();
        }
        else
        {
            $params = [
                "apiKey" => $this->config->getPublishableKey(),
                "locale" => $this->localeHelper->getStripeJsLocale(),
                "appInfo" => $this->config->getAppInfo(true),
                "options" => [
                    "betas" => \StripeIntegration\Payments\Model\Config::BETAS_CLIENT,
                    "apiVersion" => $this->config->getStripeAPIVersion()
                ],
                "successUrl" => $this->helper->getUrl('stripe/payment/index'),
                "savedMethods" => $this->paymentElement->getSavedPaymentMethods(),
                "cvcIcon" => $this->paymentMethodHelper->getCVCIcon(),
                "isOrderPlaced" => $this->paymentElement->isOrderPlaced()
            ];

            $this->setPaymentMethodSelectorLayout($params);

            // When the wallet button is enabled at the checkout, we do not want to also display it inside the Payment Element, so we disable it there.
            if ($this->expressCheckoutConfig->isEnabled("checkout_page"))
            {
                $params["wallets"] = [
                    "applePay" => "never",
                    "googlePay" => "never"
                ];
            }
            else
                $params["wallets"] = null;

            $paymentElementTerms = $this->paymentMethodOptionsService
                ->setQuote($this->quoteHelper->getQuote())
                ->getPaymentElementTerms();

            if (!empty($paymentElementTerms))
            {
                $params["terms"] = $paymentElementTerms;
            }
        }

        return $this->serializer->serialize($params);
    }

    public function getAPIModuleConfiguration()
    {
        $params = [
            "apiKey" => $this->config->getPublishableKey(),
            "locale" => $this->localeHelper->getStripeJsLocale(),
            "appInfo" => $this->config->getAppInfo(true),
            "options" => [
                "betas" => \StripeIntegration\Payments\Model\Config::BETAS_CLIENT,
                "apiVersion" => $this->config->getStripeAPIVersion()
            ],
            'elementsOptions' => $this->serializer->serialize($this->getElementOptions())
        ];

        return $this->serializer->serialize($params);
    }

    public function getAdminParams()
    {
        $params = [
            "apiKey" => $this->config->getPublishableKey(),
            "locale" => $this->localeHelper->getStripeJsLocale(),
            "appInfo" => $this->config->getAppInfo(true)
        ];

        return $this->serializer->serialize($params);
    }

    public function getMultishippingParams()
    {
        $params = [
            "apiKey" => $this->config->getPublishableKey(),
            "locale" => $this->localeHelper->getStripeJsLocale(),
            "appInfo" => $this->config->getAppInfo(true),
            "savedMethods" => $this->customer->getSavedPaymentMethods(null, true)
        ];

        $this->setPaymentMethodSelectorLayout($params);

        return $this->serializer->serialize($params);
    }

    public function getMyPaymentMethodsParams($customerId)
    {
        if (!$this->config->isEnabled())
            return $this->serializer->serialize([]);

        $params = [
            "apiKey" => $this->config->getPublishableKey(),
            "locale" => $this->localeHelper->getStripeJsLocale(),
            "currency" => strtolower($this->helper->getCurrentCurrencyCode()),
            "appInfo" => $this->config->getAppInfo(true),
            "options" => [
                "betas" => \StripeIntegration\Payments\Model\Config::BETAS_CLIENT,
                "apiVersion" => $this->config->getStripeAPIVersion()
            ],
            "returnUrl" => $this->helper->getUrl('stripe/customer/paymentmethods')
        ];

        return $this->serializer->serialize($params);
    }

    public function getWalletParams()
    {
        $params = [
            "apiKey" => $this->config->getPublishableKey(),
            "locale" => $this->localeHelper->getStripeJsLocale(),
            "appInfo" => $this->config->getAppInfo(true),
            "options" => [
                "betas" => \StripeIntegration\Payments\Model\Config::BETAS_CLIENT,
                "apiVersion" => $this->config->getStripeAPIVersion()
            ]
        ];

        return $this->serializer->serialize($params);
    }

    // Used to initialize the Elements object at the checkout page
    public function getElementOptions()
    {
        $quote = $this->quoteHelper->getQuote();
        $currency = ($quote && $quote->getQuoteCurrencyCode()) ? $quote->getQuoteCurrencyCode() : $this->helper->getCurrentCurrencyCode();
        $amount = ($quote && $quote->getGrandTotal()) ? $quote->getGrandTotal() : 0;
        $stripeAmount = $this->helper->convertMagentoAmountToStripeAmount($amount, $currency);

        $options = [
            "mode" => "payment",
            "locale" => $this->localeHelper->getStripeJsLocale(),
            "paymentMethodCreation" => "manual",
            "currency" => strtolower($currency),
            "appearance" => [
                "theme" => "stripe",
                "variables" => [
                    "colorText" => "#32325d",
                    "fontFamily" => '"Open Sans","Helvetica Neue", Helvetica, Arial, sans-serif'
                ],
            ]
        ];

        if ($stripeAmount > 0)
            $options["amount"] = $stripeAmount;

        if ($this->config->getPaymentAction() == "order")
        {
            $options["mode"] = "setup";
            $options["setupFutureUsage"] = "off_session";
            unset($options["amount"]);
        }

        if ($this->config->isEnabled() && $this->config->isSubscriptionsEnabled())
        {
            if ($this->quoteHelper->hasSubscriptions())
            {
                // Regular products may also exist in this cart. We still go for subscribe mode.
                $options["mode"] = "subscription";
            }
        }

        $paymentMethodTypes = $this->paymentMethodTypesHelper->getPaymentMethodTypes();
        $pmc = $this->config->getPaymentMethodConfiguration();

        if ($options["mode"] == "payment" && $paymentMethodTypes)
        {
            $options["paymentMethodTypes"] = $paymentMethodTypes;
        }
        else if ($pmc)
        {
            $options['paymentMethodConfiguration'] = $pmc;
        }

        return $options;
    }

    public function getExpressCheckoutElementsOptions($resolvePayload, $viewingProductId = null)
    {
        $quote = $this->quoteHelper->getQuote();
        $currency = ($quote && $quote->getQuoteCurrencyCode()) ? $quote->getQuoteCurrencyCode() : $this->helper->getCurrentCurrencyCode();

        if (!empty($resolvePayload['lineItems']))
        {
            $stripeAmount = 0;
            foreach ($resolvePayload['lineItems'] as $item)
            {
                $stripeAmount += $item['amount'];
            }
        }
        else
        {
            $amount = ($quote && $quote->getGrandTotal()) ? $quote->getGrandTotal() : 0;
            $stripeAmount = $this->helper->convertMagentoAmountToStripeAmount($amount, $currency);
        }

        $options = [
            "mode" => $this->getECEMode($viewingProductId),
            "locale" => $this->localeHelper->getStripeJsLocale(),
            "paymentMethodTypes" => $this->expressCheckoutConfig->getPaymentMethodTypes(),
            "appearance" => [
                "theme" => "stripe",
                "variables" => [
                    "colorText" => "#32325d",
                    "fontFamily" => '"Open Sans","Helvetica Neue", Helvetica, Arial, sans-serif'
                ],
            ],
            "currency" => strtolower($currency)
        ];

        if ($options["mode"] == "setup")
        {
            $options["setup_future_usage"] = "off_session";
        }
        else
        {
            $options["amount"] = $stripeAmount;
        }

        return $options;
    }

    public function getECEMode($viewingProductId)
    {
        $viewingSubscriptionProduct = $this->_isSubscriptionProduct($viewingProductId);
        return $this->config->getECEMode($viewingSubscriptionProduct);
    }

    private function _isSubscriptionProduct($productId)
    {
        if (!is_numeric($productId))
            return false;

        if ($this->subscriptionProductFactory->create()->fromProductId($productId)->isSubscriptionProduct())
            return true;

        $product = $this->productHelper->getProduct($productId);

        // If it is a configurable product, and any of its configurable options is a subscription product, return true
        if ($product && $product->getTypeId() == "configurable")
        {
            $children = $product->getTypeInstance()->getUsedProducts($product);
            foreach ($children as $child)
            {
                if ($this->subscriptionProductFactory->create()->fromProductId($child->getEntityId())->isSubscriptionProduct())
                    return true;
            }
        }

        // If it is a bundle product, and any of its bundle options is a subscription product, return true
        if ($product && $product->getTypeId() == "bundle")
        {
            $bundleType = $product->getTypeInstance();

            // Load the selections (sub-products) for the bundle product
            $optionIds = $bundleType->getOptionsIds($product);
            $selections = $bundleType->getSelectionsCollection($optionIds, $product);

            foreach ($selections as $selection)
            {
                $productId = $selection->getProductId();
                if ($this->subscriptionProductFactory->create()->fromProductId($productId)->isSubscriptionProduct())
                {
                    return true;
                }
            }
        }

        return false;
    }

    public function setPaymentMethodSelectorLayout(&$params)
    {
        if ($this->config->isVerticalLayout())
        {
            $params["layout"] = [
                "type" => "accordion",
                "defaultCollapsed" => false,
                "radios" => true,
                "spacedAccordionItems" => false,
                "visibleAccordionItemsCount" => 0
            ];
        }
    }
}
