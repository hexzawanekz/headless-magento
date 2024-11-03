<?php

declare(strict_types=1);

namespace StripeIntegration\Payments\Helper;

class SetupIntent
{
    public const ONLINE_ACTIONS = [
        'three_d_secure_redirect',
        'use_stripe_sdk',
        'redirect_to_url',
        'verify_with_microdeposits'
    ];

    private $config;
    private $helper;
    private $customer;
    private $remoteAddress;
    private $httpHeader;
    private $paymentMethodFactory;
    private $orderHelper;
    private $paymentMethodTypesHelper;
    private $checkoutFlow;

    public function __construct(
        \StripeIntegration\Payments\Model\Stripe\PaymentMethodFactory $paymentMethodFactory,
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Model\Checkout\Flow $checkoutFlow,
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Helper\Order $orderHelper,
        \StripeIntegration\Payments\Helper\PaymentMethodTypes $paymentMethodTypesHelper,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress,
        \Magento\Framework\HTTP\Header $httpHeader
    ) {
        $this->paymentMethodFactory = $paymentMethodFactory;
        $this->config = $config;
        $this->checkoutFlow = $checkoutFlow;
        $this->helper = $helper;
        $this->customer = $helper->getCustomerModel();
        $this->remoteAddress = $remoteAddress;
        $this->httpHeader = $httpHeader;
        $this->orderHelper = $orderHelper;
        $this->paymentMethodTypesHelper = $paymentMethodTypesHelper;
    }

    public function getCreateParams($order)
    {
        $description = $this->orderHelper->getOrderDescription($order);

        if (!$this->customer->getStripeId())
        {
            $this->customer->createStripeCustomerIfNotExists(false, $order);
        }

        $params = [
            "use_stripe_sdk" => true,
            "customer" => $this->customer->getStripeId(),
            "description" => $description,
            "metadata" => $this->config->getMetadata($order),
            "confirm" => true,
            "usage" => "off_session",
            "return_url" => $this->helper->getUrl("stripe/payment/index")
        ];

        $isExpressCheckout = $order && $order->getPayment()->getAdditionalInformation("confirmation_token") && $this->checkoutFlow->isExpressCheckout;
        if ($isExpressCheckout)
        {
            $params["confirmation_token"] = $order->getPayment()->getAdditionalInformation("confirmation_token");
            $paymentMethodTypes = $this->paymentMethodTypesHelper->getPaymentMethodTypes(true);
            $params["payment_method_types"] = $paymentMethodTypes;
        }
        else
        {
            $paymentMethodId = $order->getPayment()->getAdditionalInformation("token");
            $paymentMethod = $this->paymentMethodFactory->create()->fromPaymentMethodId($paymentMethodId)->getStripeObject();

            $params["automatic_payment_methods"] = [ 'enabled' => 'true' ];
            $params["payment_method"] = $paymentMethod->id;
            $params["mandate_data"] = $this->getMandateData($paymentMethod);
        }

        $customerEmail = $order->getCustomerEmail();
        if ($customerEmail && $this->config->isReceiptEmailsEnabled())
            $params["receipt_email"] = $customerEmail;

        return $params;
    }

    public function getConfirmParams($order)
    {
        $params = [
            "use_stripe_sdk" => true,
            "return_url" => $this->helper->getUrl("stripe/payment/index")
        ];

        if ($order && $order->getPayment()->getAdditionalInformation("confirmation_token"))
        {
            $params["confirmation_token"] = $order->getPayment()->getAdditionalInformation("confirmation_token");
        }
        else
        {
            $paymentMethodId = $order->getPayment()->getAdditionalInformation("token");
            $paymentMethod = $this->paymentMethodFactory->create()->fromPaymentMethodId($paymentMethodId)->getStripeObject();

            $params["payment_method"] = $order->getPayment()->getAdditionalInformation("token");
            $params["mandate_data"] = $this->getMandateData($paymentMethod);
        }

        return $params;
    }

    public function getSavePaymentMethodParams($paymentMethod)
    {
        if (!$this->customer->getStripeId())
        {
            $this->customer->createStripeCustomerIfNotExists();
        }

        $params = [
            "use_stripe_sdk" => true,
            "payment_method" => $paymentMethod->id,
            "customer" => $this->customer->getStripeId(),
            "confirm" => true,
            "usage" => "off_session",
            "automatic_payment_methods" => [ 'enabled' => 'true' ],
            "mandate_data" => $this->getMandateData($paymentMethod),
            "return_url" => $this->helper->getUrl("stripe/customer/paymentmethods")
        ];

        return $params;
    }

    public function requiresOnlineAction($setupIntent)
    {
        if ($setupIntent->status == "requires_action"
            && !empty($setupIntent->next_action->type)
            && in_array($setupIntent->next_action->type, self::ONLINE_ACTIONS)
        )
        {
            return true;
        }

        return false;
    }

    private function getMandateData($paymentMethod)
    {
        $remoteAddress = $this->remoteAddress->getRemoteAddress();
        $userAgent = $this->httpHeader->getHttpUserAgent();
        $unsupportedMethods = ['afterpay_clearpay', 'blik'];

        if (!$remoteAddress || !$userAgent || empty($paymentMethod->type) || in_array($paymentMethod->type, $unsupportedMethods))
        {
            return [];
        }

        $mandateData = [
            "customer_acceptance" => [
                "type" => "online",
                "online" => [
                    "ip_address" => $remoteAddress,
                    "user_agent" => $userAgent,
                ]
            ]
        ];

        return $mandateData;
    }

    public function isSuccessful($setupIntent)
    {
        // After required actions are handled, the PaymentIntent moves to processing for asynchronous payment methods, such as bank debits.
        // https://docs.stripe.com/payments/paymentintents/lifecycle#intent-statuses
        return $setupIntent->status === "succeeded" || $setupIntent->status === "processing";
    }
}