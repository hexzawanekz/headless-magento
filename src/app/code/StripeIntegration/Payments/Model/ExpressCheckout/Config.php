<?php

namespace StripeIntegration\Payments\Model\ExpressCheckout;

class Config
{
    // State
    private $storeId = null;
    private $isEnabled = null;
    private $activeLocations = [];
    private $allowGuestCheckout = null;
    private $sellerName = null;
    private $buttonHeight = null;
    private $sortOrderMethod = null;
    private $buttonTheme = [];
    private $buttonType = [];
    private $paymentMethodOrder = [];
    private $isApplePayEnabled = null;
    private $isGooglePayEnabled = null;
    private $isLinkEnabled = null;
    private $isPaypalEnabled = null;

    // Comstructor properties
    private $customerSession;
    private $storeHelper;
    private $configHelper;
    private $areaCodeHelper;
    private $checkoutSessionHelper;
    private $config;

    public function __construct(
        \Magento\Customer\Model\Session $customerSession,
        \StripeIntegration\Payments\Helper\Store $storeHelper,
        \StripeIntegration\Payments\Helper\Config $configHelper,
        \StripeIntegration\Payments\Helper\AreaCode $areaCodeHelper,
        \StripeIntegration\Payments\Helper\CheckoutSession $checkoutSessionHelper,
        \StripeIntegration\Payments\Model\Config $config
    )
    {
        $this->customerSession = $customerSession;
        $this->storeHelper = $storeHelper;
        $this->configHelper = $configHelper;
        $this->areaCodeHelper = $areaCodeHelper;
        $this->checkoutSessionHelper = $checkoutSessionHelper;
        $this->config = $config;

        $this->isEnabled = $this->getConfigData('payment/stripe_payments_express/global_enabled');

        if (!$this->isEnabled)
            return;

        $this->isApplePayEnabled = $this->getConfigData('payment/stripe_payments_express/apple_pay_enabled');
        $this->isGooglePayEnabled = $this->getConfigData('payment/stripe_payments_express/google_pay_enabled');
        $this->isLinkEnabled = $this->getConfigData('payment/stripe_payments_express/link_enabled');
        $this->isPaypalEnabled = $this->getConfigData('payment/stripe_payments_express/paypal_enabled');
        $this->isEnabled = $this->isApplePayEnabled || $this->isGooglePayEnabled || $this->isLinkEnabled || $this->isPaypalEnabled;

        if (!$this->isEnabled)
            return;

        $this->activeLocations = explode(',', (string)$this->getConfigData("payment/stripe_payments_express/enabled"));
        $this->allowGuestCheckout = (bool)$this->getConfigData("checkout/options/guest_checkout");
        $this->sellerName = $this->_getSellerNameConfig();
        $this->buttonHeight = $this->_getButtonHeightConfig();
        $this->sortOrderMethod = $this->getConfigData("payment/stripe_payments_express/sort_order");
        $this->buttonTheme = $this->_getButtonThemeConfig();
        $this->buttonType = $this->_getButtonTypeConfig();
        $this->paymentMethodOrder = $this->_getPaymentMethodOrderConfig();
    }

    private function getConfigData($path)
    {
        if (empty($this->storeId))
            $this->storeId = $this->storeHelper->getStoreId();

        return $this->configHelper->getConfigData($path, $this->storeId);
    }

    private function canCheckout()
    {
        if ($this->customerSession->isLoggedIn())
            return true;

        return $this->allowGuestCheckout;
    }

    public function getActiveLocations()
    {
        return $this->activeLocations;
    }

    public function isEnabled($location)
    {
        if (!$this->config->initStripe())
            return false;

        if (!$this->isEnabled)
            return false;

        if (!in_array($location, $this->activeLocations))
            return false;

        if ($this->checkoutSessionHelper->isSubscriptionUpdate())
            return false;

        if ($this->checkoutSessionHelper->isSubscriptionReactivate())
        {
            // Because confirmation tokens cannot yet be used to set the default_payment_method after the re-activation
            // We can only set https://docs.stripe.com/api/subscriptions/update#update_subscription-default_payment_method
            // Currently set via Model/PaymentElement.php::updateSubscriptionFromOrder()
            return false;
        }

        if ($this->areaCodeHelper->isAdmin())
            return false;

        if (!$this->storeHelper->isSecure())
            return false;

        if (!$this->canCheckout())
            return false;

        return true;
    }

    public function getButtonOptions()
    {
        $options = [
            'buttonHeight' => $this->buttonHeight,
            'buttonTheme' => $this->buttonTheme,
            'buttonType' => $this->buttonType
        ];

        if ($this->sortOrderMethod == "custom")
            $options['paymentMethodOrder'] = $this->paymentMethodOrder;

        $options['paymentMethods'] = [
            'applePay' => $this->isApplePayEnabled ? 'auto' : 'never',
            'googlePay' => $this->isGooglePayEnabled ? 'auto' : 'never',
            'link' => $this->isLinkEnabled ? 'auto' : 'never',
            'paypal' => $this->isPaypalEnabled ? 'auto' : 'never'
        ];

        return $options;
    }

    public function getSellerName()
    {
        return $this->sellerName;
    }

    private function _getSellerNameConfig()
    {
        $sellerName = $this->getConfigData('payment/stripe_payments_express/seller_name');
        if (empty($this->sellerName))
            return __("Order Total");

        return $sellerName;
    }

    private function _getButtonHeightConfig()
    {
        $buttonHeight = $this->getConfigData('payment/stripe_payments_express/button_height');
        if (!is_numeric($buttonHeight))
            return 50;
        else if ((int)$buttonHeight < 40)
            return 40;
        else if ((int)$buttonHeight > 55)
            return 55;

        return (int)$buttonHeight;
    }

    private function _getButtonThemeConfig()
    {
        $buttonTheme = [];

        if ($this->isApplePayEnabled)
            $buttonTheme['applePay'] = $this->getConfigData('payment/stripe_payments_express/apple_pay_button_theme');

        if ($this->isGooglePayEnabled)
            $buttonTheme['googlePay'] = $this->getConfigData('payment/stripe_payments_express/google_pay_button_theme');

        if ($this->isPaypalEnabled)
            $buttonTheme['paypal'] = $this->getConfigData('payment/stripe_payments_express/paypal_button_theme');

        return $buttonTheme;
    }

    private function _getButtonTypeConfig()
    {
        $buttonType = [];

        if ($this->isApplePayEnabled)
            $buttonType['applePay'] = $this->getConfigData('payment/stripe_payments_express/apple_pay_button_type');

        if ($this->isGooglePayEnabled)
            $buttonType['googlePay'] = $this->getConfigData('payment/stripe_payments_express/google_pay_button_type');

        if ($this->isPaypalEnabled)
            $buttonType['paypal'] = $this->getConfigData('payment/stripe_payments_express/paypal_button_type');

        return $buttonType;
    }

    private function _getPaymentMethodOrderConfig()
    {
        $paymentMethodOrder = [];

        $sortOrders = [];

        if ($this->isApplePayEnabled)
            $sortOrders['applePay'] = $this->getConfigData('payment/stripe_payments_express/apple_pay_sort_order');

        if ($this->isGooglePayEnabled)
            $sortOrders['googlePay'] = $this->getConfigData('payment/stripe_payments_express/google_pay_sort_order');

        if ($this->isLinkEnabled)
            $sortOrders['link'] = $this->getConfigData('payment/stripe_payments_express/link_sort_order');

        if ($this->isPaypalEnabled)
            $sortOrders['paypal'] = $this->getConfigData('payment/stripe_payments_express/paypal_sort_order');

        foreach ($sortOrders as $key => $index)
        {
            if (empty($index))
                $index = 0;

            // Find the last occurence of $index, or the first occurence of a higher number, and insert the key there
            $insertIndex = count($paymentMethodOrder);
            for ($i = 0; $i < count($paymentMethodOrder); $i++)
            {
                if ($paymentMethodOrder[$i] > $index)
                {
                    $insertIndex = $i;
                    break;
                }
                else if ($paymentMethodOrder[$i] == $index)
                {
                    $insertIndex = $i + 1;
                }
            }

            array_splice($paymentMethodOrder, $insertIndex, 0, $key);
        }

        return $paymentMethodOrder;
    }

    public function getPaymentMethodTypes()
    {
        $pmt = ["card"];

        if ($this->isLinkEnabled)
            $pmt[] = "link";

        return $pmt;
    }
}