<?php

namespace StripeIntegration\Payments\Model\Ui;

use Magento\Framework\Exception\LocalizedException;
use Magento\Checkout\Model\ConfigProviderInterface;
use StripeIntegration\Payments\Model\Config;

/**
 * Class ConfigProvider
 */
class ConfigProvider implements ConfigProviderInterface
{
    private $assetRepo;
    private $cardIcons;
    private $config;
    private $expressCheckoutConfig;
    private $helper;
    private $initParams;
    private $logger;
    private $paymentMethodHelper;
    private $request;
    private $serializer;
    private $subscriptionsHelper;
    private $urlBuilder;
    private $response;
    private $localeHelper;
    private $quoteHelper;

    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\View\Asset\Repository $assetRepo,
        \Magento\Framework\Serialize\SerializerInterface $serializer,
        \Magento\Framework\App\ResponseInterface $response,
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Helper\Quote $quoteHelper,
        \StripeIntegration\Payments\Model\ExpressCheckout\Config $expressCheckoutConfig,
        \StripeIntegration\Payments\Model\Adminhtml\Source\CardIconsSpecific $cardIcons,
        \StripeIntegration\Payments\Helper\Subscriptions $subscriptionsHelper,
        \StripeIntegration\Payments\Helper\InitParams $initParams,
        \StripeIntegration\Payments\Helper\PaymentMethod $paymentMethodHelper,
        \StripeIntegration\Payments\Helper\Locale $localeHelper
    )
    {
        $this->request = $request;
        $this->assetRepo = $assetRepo;
        $this->serializer = $serializer;
        $this->response = $response;
        $this->config = $config;
        $this->helper = $helper;
        $this->expressCheckoutConfig = $expressCheckoutConfig;
        $this->cardIcons = $cardIcons;
        $this->subscriptionsHelper = $subscriptionsHelper;
        $this->initParams = $initParams;
        $this->paymentMethodHelper = $paymentMethodHelper;
        $this->localeHelper = $localeHelper;
        $this->quoteHelper = $quoteHelper;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        $data = [];
        $checkoutInitParams = $this->serializer->unserialize($this->initParams->getCheckoutParams());

        $data = [
            'payment' => [
                'stripe_payments' => [
                    'enabled' => $this->config->isEnabled(),
                    'initParams' => $checkoutInitParams,
                    'icons' => $this->getIcons(),
                    'pmIcons' => $this->paymentMethodHelper->getPaymentMethodDetails(),
                    'elementOptions' => $this->initParams->getElementOptions(),
                    'hasTrialSubscriptions' => false,
                    'trialingSubscriptions' => null
                ],
                'express_checkout' => [
                    'enabled' => $this->expressCheckoutConfig->isEnabled('checkout_page'),
                    'initParams' => $this->serializer->unserialize($this->initParams->getWalletParams()),
                    'buttonConfig' => $this->expressCheckoutConfig->getButtonOptions()
                ],
                'stripe_payments_bank_transfers' => [
                    'elementOptions' => $this->getBankTransfersElementOptions(),
                    'initParams' => $checkoutInitParams
                ]
            ]
        ];

        if ($this->config->isEnabled() && $this->config->isSubscriptionsEnabled())
        {
            // These are a bit more resource intensive, so we only want to run them if the module is enabled
            $data['payment']['stripe_payments']['hasTrialSubscriptions'] = $this->subscriptionsHelper->hasTrialSubscriptions();
            $data['payment']['stripe_payments']['trialingSubscriptions'] = $this->subscriptionsHelper->getTrialingSubscriptionsAmounts();

            $subscriptionUpdateDetails = $this->getFrontendSubscriptionUpdateDetails();
            if ($subscriptionUpdateDetails)
            {
                $data['payment']['stripe_payments']['subscriptionUpdateDetails'] = $subscriptionUpdateDetails;
            }
        }

        return $data;
    }

    protected function getBankTransfersElementOptions()
    {
        $options = [
            "mode" => "payment",
            "locale" => $this->localeHelper->getStripeJsLocale(),
            "paymentMethodCreation" => "manual",
            "appearance" => [
                "theme" => "stripe",
                "variables" => [
                    "colorText" => "#32325d",
                    "fontFamily" => '"Open Sans","Helvetica Neue", Helvetica, Arial, sans-serif'
                ],
            ],
            "payment_method_types" => ["customer_balance"]
        ];

        return $options;
    }

    protected function getFrontendSubscriptionUpdateDetails()
    {
        try
        {
            $subscriptionUpdateDetails = $this->helper->getCheckoutSession()->getSubscriptionUpdateDetails();
            if (!empty($subscriptionUpdateDetails['_data']['subscription_id']))
            {
                // Ensure that the subscription can be updated
                $subscription = $this->config->getStripeClient()->subscriptions->retrieve($subscriptionUpdateDetails['_data']['subscription_id'], []);
                if ($subscription->status != "active")
                {
                    $this->subscriptionsHelper->cancelSubscriptionUpdate(true);
                    $message = __("This subscription cannot be updated because it is not active.");
                    $this->helper->addError($message);
                    $this->redirect('stripe/customer/subscriptions');
                    return null;
                }

                // Ensure that the product is still in the cart
                if (empty($subscriptionUpdateDetails['_data']['product_ids']))
                {
                    $this->helper->logError("Canceling subscription update: No product IDs set.");
                    $this->helper->getCheckoutSession()->unsSubscriptionUpdateDetails();
                    return null;
                }

                $quote = $this->quoteHelper->getQuote();

                unset($subscriptionUpdateDetails['_data']); // Unset sensitive _data and return the remaining info for front-end display

                $subscriptionUpdateDetails["success_url"] = $this->helper->getUrl("stripe/customer/subscriptions", ["updateSuccess" => 1]);
                $subscriptionUpdateDetails["cancel_url"] = $this->helper->getUrl("stripe/customer/subscriptions", ["updateCancel" => 1]);
                $subscriptionUpdateDetails["is_virtual"] = $quote->getIsVirtual();
                return $subscriptionUpdateDetails;
            }

            return null;
        }
        catch (\Exception $e)
        {
            $this->helper->logError("Canceling subscription update: " . $e->getMessage());
            $this->helper->getCheckoutSession()->unsSubscriptionUpdateDetails();
            $this->helper->logError($e->getMessage(), $e->getTraceAsString());
            return null;
        }
    }

    /**
     * Retrieve url of a view file
     *
     * @param string $fileId
     * @param array $params
     * @return string
     */
    public function getViewFileUrl($fileId, array $params = [])
    {
        try {
            $params = array_merge(['_secure' => $this->request->isSecure()], $params);
            return $this->assetRepo->getUrlWithParams($fileId, $params);
        } catch (LocalizedException $e) {
            $this->logger->critical($e);
            return $this->urlBuilder->getUrl('', ['_direct' => 'core/index/notFound']);
        }
    }

    public function getIcons()
    {
        $icons = [];
        $displayIcons = $this->config->displayCardIcons();
        switch ($displayIcons)
        {
            // All
            case 0:
                $options = $this->cardIcons->toOptionArray();
                foreach ($options as $option)
                {
                    $code = $option["value"];
                    $icons[] = [
                        'code' => $code,
                        'name' => $option["label"],
                        'path' => $this->getViewFileUrl("StripeIntegration_Payments::img/cards/$code.svg")
                    ];
                }
                return $icons;
            // Specific
            case 1:
                $specific = explode(",", $this->config->getCardIcons());
                foreach ($specific as $code)
                {
                    if (empty($code))
                        continue;

                    $icons[] = [
                        'code' => $code,
                        'name' => null,
                        'path' => $this->getViewFileUrl("StripeIntegration_Payments::img/cards/$code.svg")
                    ];
                }
                return $icons;
            // Disabled
            default:
                return [];
        }
    }

    public function redirect($path)
    {
        $this->response->clearHeaders()->setNoCacheHeaders();
        $url = $this->helper->getUrl($path);
        $this->response->setRedirect($url)->sendResponse();
    }
}
