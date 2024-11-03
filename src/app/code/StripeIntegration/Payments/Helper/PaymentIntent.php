<?php

namespace StripeIntegration\Payments\Helper;

class PaymentIntent
{
    public const ONLINE_ACTIONS = [
        'three_d_secure_redirect',
        'use_stripe_sdk',
        'redirect_to_url'
    ];

    public const CANCELABLE_STATUSES = [
        'requires_payment_method',
        'requires_capture',
        'requires_confirmation',
        'requires_action',
        'requires_source',
        'processing'
    ];

    private $remoteAddress;
    private $httpHeader;
    private $cache;
    private $quoteHelper;
    private $stripePaymentMethodFactory;
    private $areaCodeHelper;
    private $urlHelper;
    private $paymentMethodOptionsService;

    public function __construct(
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress,
        \Magento\Framework\HTTP\Header $httpHeader,
        \Magento\Framework\App\CacheInterface $cache,
        \StripeIntegration\Payments\Helper\Quote $quoteHelper,
        \StripeIntegration\Payments\Helper\AreaCode $areaCodeHelper,
        \StripeIntegration\Payments\Helper\Url $urlHelper,
        \StripeIntegration\Payments\Model\Stripe\PaymentMethodFactory $stripePaymentMethodFactory,
        \StripeIntegration\Payments\Service\PaymentMethodOptionsService $paymentMethodOptionsService
    )
    {
        $this->remoteAddress = $remoteAddress;
        $this->httpHeader = $httpHeader;
        $this->cache = $cache;
        $this->quoteHelper = $quoteHelper;
        $this->areaCodeHelper = $areaCodeHelper;
        $this->urlHelper = $urlHelper;
        $this->stripePaymentMethodFactory = $stripePaymentMethodFactory;
        $this->paymentMethodOptionsService = $paymentMethodOptionsService;
    }

    public function getConfirmParams($order, $paymentIntent, $includeCvcToken = false, $savePaymentMethod = null)
    {
        $confirmParams = [
            "use_stripe_sdk" => true
        ];

        $savePaymentMethod = null;
        $paymentMethod = null;
        $isPaymentMethodSaved = false;
        if ($order->getPayment()->getAdditionalInformation("token"))
        {
            // We are using a saved payment method token
            $confirmParams["payment_method"] = $order->getPayment()->getAdditionalInformation("token");

            $paymentMethod = $this->stripePaymentMethodFactory->create()->fromPaymentMethodId($confirmParams["payment_method"])->getStripeObject();
            if (!empty($paymentMethod->customer))
            {
                $savePaymentMethod = false;
                $isPaymentMethodSaved = true;
            }
        }
        else if ($order->getPayment()->getAdditionalInformation("confirmation_token"))
        {
            $confirmParams["confirmation_token"] = $order->getPayment()->getAdditionalInformation("confirmation_token");
        }

        if (!empty($paymentIntent->automatic_payment_methods->enabled))
            $confirmParams["return_url"] = $this->urlHelper->getUrl('stripe/payment/index');

        $quote = $this->quoteHelper->loadQuoteById($order->getQuoteId());
        $options = $this->paymentMethodOptionsService
            ->setQuote($quote)
            ->setSavePaymentMethod($savePaymentMethod)
            ->getPaymentMethodOptions();

        if (!empty($options))
        {
            $confirmParams["payment_method_options"] = $options;
        }

        if ($this->areaCodeHelper->isAdmin())
        {
            if ($order->getPayment()->getAdditionalInformation("save_payment_method") && !$isPaymentMethodSaved)
            {
                // Override the existing value
                $confirmParams["payment_method_options"]["card"]["setup_future_usage"] = "off_session";
            }

            if (!$this->cache->load("no_moto_gate"))
            {
                $confirmParams["payment_method_options"]["card"]["moto"] = "true";
            }
            else if ($isPaymentMethodSaved)
            {
                $confirmParams["off_session"] = true;
            }
        }

        if ($includeCvcToken && $order->getPayment()->getAdditionalInformation("cvc_token") && $paymentIntent->object != "setup_intent")
        {
            $confirmParams["payment_method_options"]["card"]['cvc_token'] = $order->getPayment()->getAdditionalInformation("cvc_token");
        }

        $mandateData = $this->getMandateData($paymentMethod, $paymentIntent);
        if (!empty($mandateData))
        {
            $confirmParams = array_merge($confirmParams, $mandateData);
        }

        return $confirmParams;
    }

    public function getAdminConfirmParams($order, $paymentIntent)
    {
        $params = $this->getConfirmParams($order, $paymentIntent);

        $paymentMethod = $this->stripePaymentMethodFactory->create()->fromPaymentMethodId($params['payment_method'])->getStripeObject();
        $isCard = in_array($paymentMethod->type, ["card", "link"]);
        $moto = isset($params['payment_method_options']['card']['moto']) ? $params['payment_method_options']['card']['moto'] : false;

        if (isset($params['payment_method_options']))
        {
            // We don't want to authorize only and we don't want to setup future usage, but we want to keep the moto parameter
            unset($params["payment_method_options"]);
        }

        if ($isCard && $moto)
        {
            $params['payment_method_options']['card']['moto'] = $moto;
        }
        else
        {
            $params['off_session'] = true;
        }

        if (isset($params['use_stripe_sdk']))
            unset($params['use_stripe_sdk']);

        return $params;
    }

    public function getMultishippingConfirmParams($paymentMethodId, $paymentIntent)
    {
        $confirmParams = [
            'payment_method' => $paymentMethodId,
            'use_stripe_sdk' => true,
            'setup_future_usage' => 'off_session'
        ];

        $paymentMethod = $this->stripePaymentMethodFactory->create()->fromPaymentMethodId($paymentMethodId)->getStripeObject();
        $mandateData = $this->getMandateData($paymentMethod, $paymentIntent);
        if (!empty($mandateData))
        {
            $confirmParams = array_merge($confirmParams, $mandateData);
        }

        if (!empty($paymentIntent->automatic_payment_methods->enabled))
            $confirmParams["return_url"] = $this->urlHelper->getUrl('stripe/payment/index');

        return $confirmParams;
    }

    public function getDelayedSubscriptionSetupConfirmParams($order, $paymentIntent)
    {
        $confirmParams = $this->getConfirmParams($order, $paymentIntent);

        // Unset setup_future_usage from payment method options, it cannot be used with mandate_data
        // plus it has already been saved in this flow.
        if (!empty($confirmParams["payment_method_options"]))
        {
            foreach ($confirmParams["payment_method_options"] as $pmCode => $options)
            {
                if (!empty($options["setup_future_usage"]))
                {
                    unset($confirmParams["payment_method_options"][$pmCode]["setup_future_usage"]);
                }
            }
        }

        return $confirmParams;
    }

    public function isSuccessful($paymentIntent)
    {
        if ($paymentIntent->status == "processing" && !$this->isAsyncProcessing($paymentIntent))
        {
            // https://stripe.com/docs/payments/paymentintents/lifecycle#intent-statuses
            return true;
        }
        else if (in_array($paymentIntent->status, ['succeeded', 'requires_capture']))
        {
            return true;
        }

        return false;
    }

    public function isAsyncProcessing($paymentIntent)
    {
        if ($paymentIntent->status == "processing" && (empty($paymentIntent->processing->type) || $paymentIntent->processing->type != "card"))
        {
            // https://stripe.com/docs/payments/paymentintents/lifecycle#intent-statuses
            return true;
        }

        return false;
    }

    public function requiresOfflineAction($paymentIntent)
    {
        if ($paymentIntent->status == "requires_action"
            && !empty($paymentIntent->next_action->type)
            && !in_array($paymentIntent->next_action->type, self::ONLINE_ACTIONS)
        )
        {
            return true;
        }

        return false;
    }

    public function canCancel($paymentIntent)
    {
        return in_array($paymentIntent->status, self::CANCELABLE_STATUSES)
            && empty($paymentIntent->invoice); // Subscription PIs cannot be canceled
    }

    public function canConfirm($paymentIntent)
    {
        return $paymentIntent->status == "requires_confirmation";
    }

    public function isSetupIntent($id)
    {
        if (!empty($id) && strpos($id, "seti_") === 0)
            return true;

        return false;
    }

    protected function hasFinalizedInvoice($paymentIntent)
    {
        if (empty($paymentIntent->invoice))
            return false;

        if (is_string($paymentIntent->invoice))
            $invoice = \Stripe\Invoice::retrieve($paymentIntent->invoice);
        else
            $invoice = $paymentIntent->invoice;

        if ($invoice->status == 'open')
            return false;

        return true;
    }

    public function getUpdateableParams($params, $paymentIntent = null)
    {
        if (($paymentIntent && (
                $this->isSuccessful($paymentIntent) ||
                $this->isAsyncProcessing($paymentIntent) ||
                $this->requiresOfflineAction($paymentIntent)) ||
                $this->isSetupIntent($paymentIntent->id)
            )
            || $this->hasFinalizedInvoice($paymentIntent))
        {
            $updateableParams = [
                "description",
                "metadata"
            ];
        }
        else
        {
            $updateableParams = [
                "amount",
                "description",
                "metadata",
                "setup_future_usage",
                "shipping" // Required by certain methods like AfterPay/Clearpay
            ];

            if (empty($paymentIntent->invoice))
                $updateableParams[] = "currency";

            // If the Stripe account is not gated, adding these params will crash the PaymentIntent::update() call,
            // so we conditionally add them based on whether they exist or not in the original params
            if (!empty($params['level3']))
                $updateableParams[] = "level3";

            // We can only set the customer, we cannot change it
            if (!empty($params["customer"]) && empty($paymentIntent->customer))
            {
                $updateableParams[] = "customer";
            }
        }

        $nonEmptyParams = [];

        foreach ($updateableParams as $paramName)
        {
            if (!empty($params[$paramName]))
                $nonEmptyParams[] = $paramName;
        }

        return $nonEmptyParams;
    }

    public function getFilteredParamsForUpdate($params, $paymentIntent = null)
    {
        $newParams = [];

        foreach ($this->getUpdateableParams($params, $paymentIntent) as $key)
        {
            if (isset($params[$key]))
                $newParams[$key] = $params[$key];
            else
                $newParams[$key] = null; // Unsets it through the API
        }

        return $newParams;
    }

    public function getMandateData($paymentMethod, $intent): array
    {
        $params = [];
        $remoteAddress = $this->remoteAddress->getRemoteAddress();
        $userAgent = $this->httpHeader->getHttpUserAgent();
        $unsupportedMethods = ['afterpay_clearpay', 'blik'];

        if (!$remoteAddress || !$userAgent || empty($paymentMethod->type) || in_array($paymentMethod->type, $unsupportedMethods))
        {
            return [];
        }

        $params['mandate_data']['customer_acceptance'] = [
            "type" => "online",
            "online" => [
                "ip_address" => $remoteAddress,
                "user_agent" => $userAgent,
            ]
        ];

        return $params;
    }
}
