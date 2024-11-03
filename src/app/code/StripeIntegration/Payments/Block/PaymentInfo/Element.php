<?php

namespace StripeIntegration\Payments\Block\PaymentInfo;

use StripeIntegration\Payments\Helper\Data as StripeHelperData;

class Element extends \StripeIntegration\Payments\Block\PaymentInfo\Checkout
{
    private $paymentIntents = [];
    public $subscription = null;
    private $setupIntents = [];
    private $stripePaymentIntent;
    private $stripePaymentMethodFactory;
    private $helper;
    private $paymentsConfig;
    private $tokenHelper;
    private $orderHelper;
    private $paymentMethod;

    /**
     * @var StripeHelperData
     */
    protected $stripeHelperData;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Payment\Gateway\ConfigInterface $config,
        \Magento\Framework\App\RequestInterface $request,
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Helper\Order $orderHelper,
        \StripeIntegration\Payments\Helper\PaymentMethod $paymentMethodHelper,
        \StripeIntegration\Payments\Helper\Subscriptions $subscriptions,
        \StripeIntegration\Payments\Helper\Api $api,
        \StripeIntegration\Payments\Helper\Token $tokenHelper,
        \StripeIntegration\Payments\Helper\AreaCode $areaCodeHelper,
        \StripeIntegration\Payments\Model\Config $paymentsConfig,
        \StripeIntegration\Payments\Model\Stripe\PaymentIntent $stripePaymentIntent,
        \StripeIntegration\Payments\Model\Stripe\PaymentMethodFactory $stripePaymentMethodFactory,
        StripeHelperData $stripeHelperData,
        array $data = []
    ) {
        parent::__construct($context, $config, $request, $areaCodeHelper, $helper, $paymentMethodHelper, $subscriptions, $api, $tokenHelper, $paymentsConfig, $stripePaymentMethodFactory, $data);

        $this->stripeHelperData = $stripeHelperData;
        $this->stripePaymentIntent = $stripePaymentIntent;
        $this->helper = $helper;
        $this->orderHelper = $orderHelper;
        $this->paymentsConfig = $paymentsConfig;
        $this->tokenHelper = $tokenHelper;
        $this->stripePaymentMethodFactory = $stripePaymentMethodFactory;
    }

    public function getTemplate()
    {
        $info = $this->getInfo();

        if (!$this->paymentsConfig->getStripeClient())
            return null;

        if (!$this->isAllowedAction())
            return 'paymentInfo/generic.phtml';

        if ($info && $info->getAdditionalInformation("is_subscription_update"))
            return 'paymentInfo/subscription_update.phtml';

        return 'paymentInfo/element.phtml';
    }

    private function getPaymentMethodToken()
    {
        $info = $this->getInfo();
        if ($info && $info->getAdditionalInformation("token"))
        {
            $token = $info->getAdditionalInformation("token");
            $token = $this->tokenHelper->cleanToken($token);
            if ($this->tokenHelper->isPaymentMethodToken($token))
                return $token;
        }

        return null;
    }

    public function getPaymentMethod()
    {
        if (isset($this->paymentMethod))
            return $this->paymentMethod;

        $paymentIntent = $this->getPaymentIntent();
        $setupIntent = $this->getSetupIntent();
        $paymentMethodToken = $this->getPaymentMethodToken();

        $paymentMethod = $paymentIntent->payment_method ??
            $setupIntent->payment_method ??
            $paymentMethodToken;

        if (isset($paymentMethod->id))
        {
            return $this->paymentMethod = $paymentMethod;
        }
        else if ($this->tokenHelper->isPaymentMethodToken($paymentMethod))
        {
            try
            {
                $paymentMethod = $this->stripePaymentMethodFactory->create()->fromPaymentMethodId($paymentMethod)->getStripeObject();
                return $this->paymentMethod = $paymentMethod;
            }
            catch (\Exception $e)
            {
                $this->helper->logInfo("Could not retrieve payment method from Stripe: " . $e->getMessage());
            }
        }

        return $this->paymentMethod = null;
    }

    public function isMultiShipping()
    {
        $paymentIntent = $this->getPaymentIntent();

        if (empty($paymentIntent->metadata["Multishipping"]))
            return false;

        return true;
    }

    public function getFormattedAmount()
    {
        /** @var ?\Stripe\PaymentIntent $paymentIntent */
        $paymentIntent = $this->getPaymentIntent();

        if (empty($paymentIntent->amount))
            return '';

        return $this->helper->formatStripePrice($paymentIntent->amount, $paymentIntent->currency);
    }

    public function getFormattedMultishippingAmount()
    {
        $total = $this->getFormattedAmount();

        $paymentIntent = $this->getPaymentIntent();

        /** @var \Magento\Payment\Model\InfoInterface $info */
        $info = $this->getInfo();
        if (!is_numeric($info->getAmountOrdered()))
            return $total;

        $partial = $this->helper->addCurrencySymbol($info->getAmountOrdered(), $paymentIntent->currency);

        return $partial;
    }

    public function getPaymentStatus()
    {
        $paymentIntent = $this->getPaymentIntent();

        return $this->getPaymentIntentStatus($paymentIntent);
    }

    public function getSubscription()
    {
        if (empty($this->subscription))
        {
            $info = $this->getInfo();
            if ($info && $info->getAdditionalInformation("subscription_id"))
            {
                try
                {
                    $subscriptionId = $info->getAdditionalInformation("subscription_id");
                    $this->subscription = $this->paymentsConfig->getStripeClient()->subscriptions->retrieve($subscriptionId);
                }
                catch (\Exception $e)
                {
                    $this->helper->logInfo("Could not retrieve subscription from Stripe: " . $e->getMessage());
                    return null;
                }
            }
        }

        return $this->subscription;
    }

    public function getCustomerId()
    {
        $info = $this->getInfo();
        if ($info && $info->getAdditionalInformation("customer_stripe_id"))
            return $info->getAdditionalInformation("customer_stripe_id");

        return null;
    }

    public function isStripeMethod()
    {
        $method = $this->getInfo()->getMethod();

        if (strpos($method, "stripe_payments") !== 0 || $method == "stripe_payments_invoice")
            return false;

        return true;
    }

    public function getPaymentIntent()
    {
        $transactionId = $this->getInfo()->getLastTransId();
        $transactionId = $this->tokenHelper->cleanToken($transactionId);

        if (empty($transactionId) || strpos($transactionId, "pi_") !== 0)
            return null;

        if (array_key_exists($transactionId, $this->paymentIntents))
            return $this->paymentIntents[$transactionId];

        try
        {
            $paymentIntent = $this->stripePaymentIntent->fromPaymentIntentId($transactionId, ['payment_method'])->getStripeObject();
            return $this->paymentIntents[$transactionId] = $paymentIntent;
        }
        catch (\Exception $e)
        {
            return $this->paymentIntents[$transactionId] = null;
        }
    }

    public function getSetupIntent()
    {
        $transactionId = $this->getInfo()->getLastTransId();
        $transactionId = $this->tokenHelper->cleanToken($transactionId);
        $clientSecret = $this->getInfo()->getAdditionalInformation("client_secret");
        $setupIntentId = null;

        if ($this->tokenHelper->isSetupIntentToken($transactionId))
            $setupIntentId = $transactionId;
        else if ($this->tokenHelper->isSetupIntentToken($clientSecret))
            $setupIntentId = $this->tokenHelper->getSetupIntentIdFromClientSecret($clientSecret);

        if (empty($setupIntentId))
            return null;

        if (array_key_exists($setupIntentId, $this->setupIntents))
            return $this->setupIntents[$setupIntentId];

        try
        {
            return $this->setupIntents[$setupIntentId] = $this->paymentsConfig->getStripeClient()->setupIntents->retrieve($setupIntentId, ['expand' => ['payment_method']]);
        }
        catch (\Exception $e)
        {
            return $this->setupIntents[$setupIntentId] = null;
        }
    }

    public function getMode()
    {
        $paymentIntent = $this->getPaymentIntent();
        $setupIntent = $this->getSetupIntent();

        if ($paymentIntent && $paymentIntent->livemode)
            return "";
        else if ($setupIntent && $setupIntent->livemode)
            return "";

        return "test/";
    }

    // For subscription updates
    public function getSubscriptionOrderUrl($orderIncrementId)
    {
        if (empty($orderIncrementId))
            return null;

        $order = $this->orderHelper->loadOrderByIncrementId($orderIncrementId);
        if (!$order || !$order->getId())
            return null;

        return $this->helper->getUrl('sales/order/view', ['order_id' => $order->getId()]);
    }

    public function getOriginalSubscriptionOrderIncrementId()
    {
        $info = $this->getInfo();
        if (!$info)
            return null;

        $incrementId = $info->getAdditionalInformation("original_order_increment_id");
        if (empty($incrementId))
            return null;

        return $incrementId;
    }

    public function getNewSubscriptionOrderIncrementId()
    {
        $info = $this->getInfo();
        if (!$info)
            return null;

        $incrementId = $info->getAdditionalInformation("new_order_increment_id");
        if (empty($incrementId))
            return null;

        return $incrementId;
    }

    public function getPreviousSubscriptionAmount()
    {
        $info = $this->getInfo();
        if (!$info)
            return null;

        return $info->getAdditionalInformation("previous_subscription_amount");
    }

    public function getFormattedSubscriptionAmount()
    {
        if ($this->getPreviousSubscriptionAmount())
            return null;

        return parent::getFormattedSubscriptionAmount();
    }

    public function getFormattedNewSubscriptionAmount()
    {
        if (!$this->getPreviousSubscriptionAmount())
            return null;

        $info = $this->getInfo();
        if ($info && $info->getAdditionalInformation("new_subscription_amount"))
            return $info->getAdditionalInformation("new_subscription_amount");

        return parent::getFormattedSubscriptionAmount();
    }

    /**
     * prepare the risk element class
     *
     * @param int $riskScore
     * @param string $riskLevel
     * @return string
     */
    public function getRiskElementClass($riskScore = 0, $riskLevel = 'NA')
    {
        return $this->stripeHelperData->getRiskElementClass($riskScore, $riskLevel);
    }
}
