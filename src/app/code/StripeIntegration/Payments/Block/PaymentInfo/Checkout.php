<?php

namespace StripeIntegration\Payments\Block\PaymentInfo;

class Checkout extends \Magento\Payment\Block\ConfigurableInfo
{
    public $charges = null;
    public $totalCharges = 0;
    public $charge = null;
    public $cards = [];
    public $subscription = null;
    public $checkoutSession = null;
    private $helper;
    private $paymentsConfig;

    private $subscriptions;
    private $paymentMethodHelper;
    private $api;
    private $stripePaymentMethodFactory;
    private $setupIntent;
    private $paymentIntent;
    private $paymentMethod;
    private $tokenHelper;
    private $request;
    private $areaCodeHelper;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Payment\Gateway\ConfigInterface $config,
        \Magento\Framework\App\RequestInterface $request,
        \StripeIntegration\Payments\Helper\AreaCode $areaCodeHelper,
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Helper\PaymentMethod $paymentMethodHelper,
        \StripeIntegration\Payments\Helper\Subscriptions $subscriptions,
        \StripeIntegration\Payments\Helper\Api $api,
        \StripeIntegration\Payments\Helper\Token $tokenHelper,
        \StripeIntegration\Payments\Model\Config $paymentsConfig,
        \StripeIntegration\Payments\Model\Stripe\PaymentMethodFactory $stripePaymentMethodFactory,
        array $data = []
    ) {
        parent::__construct($context, $config, $data);

        $this->request = $request;
        $this->areaCodeHelper = $areaCodeHelper;
        $this->helper = $helper;
        $this->subscriptions = $subscriptions;
        $this->api = $api;
        $this->paymentsConfig = $paymentsConfig;
        $this->stripePaymentMethodFactory = $stripePaymentMethodFactory;
        $this->paymentMethodHelper = $paymentMethodHelper;
        $this->tokenHelper = $tokenHelper;
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

        return 'paymentInfo/checkout.phtml';
    }

    public function isAllowedAction()
    {
        if (!$this->areaCodeHelper->isAdmin())
            return true;

        $allowedAdminActions = ["view", "new", "email"];
        $action = $this->request->getActionName();
        if (in_array($action, $allowedAdminActions))
            return true;

        return false;
    }

    public function getFormattedAmount()
    {
        $checkoutSession = $this->getCheckoutSession();

        if (empty($checkoutSession->amount_total))
            return '';

        return $this->helper->formatStripePrice($checkoutSession->amount_total, $checkoutSession->currency);
    }

    public function getFormattedSubscriptionAmount()
    {
        $subscription = $this->getSubscription();

        if (empty($subscription->plan))
            return '';

        return $this->subscriptions->formatInterval(
            $subscription->plan->amount,
            $subscription->plan->currency,
            $subscription->plan->interval_count,
            $subscription->plan->interval
        );
    }

    public function getPaymentMethod()
    {
        if (!empty($this->paymentMethod))
            return $this->paymentMethod;

        $checkoutSession = $this->getCheckoutSession();
        $paymentIntent = $this->getPaymentIntent();
        $setupIntent = $this->getSetupIntent();

        $paymentMethod = $paymentIntent->payment_method ??
            $setupIntent->payment_method ??
            $checkoutSession->subscription->default_payment_method ??
            null;

        if (isset($paymentMethod->id))
        {
            return $this->paymentMethod = $paymentMethod;
        }
        else if ($this->tokenHelper->isPaymentMethodToken($paymentMethod))
        {
            $paymentMethod = $this->stripePaymentMethodFactory->create()->fromPaymentMethodId($paymentMethod);
            return $this->paymentMethod = $paymentMethod->getStripeObject();
        }

        return null;
    }

    public function getPaymentMethodCode()
    {
        $method = $this->getPaymentMethod();

        if (!empty($method->type))
            return $method->type;

        return null;
    }

    public function getPaymentMethodName($hideLast4 = false)
    {
        $paymentMethodCode = $this->getPaymentMethodCode();

        switch ($paymentMethodCode)
        {
            case "card":
                $method = $this->getPaymentMethod();
                return $this->paymentMethodHelper->getCardLabel($method->card, $hideLast4);
            default:
                return $this->paymentMethodHelper->getPaymentMethodName($paymentMethodCode);
        }
    }

    public function getPaymentMethodIconUrl($format = null)
    {
        $method = $this->getPaymentMethod();

        if (!$method)
            return null;

        return $this->paymentMethodHelper->getIcon($method, $format);
    }

    public function getWalletIconUrl()
    {
        $method = $this->getPaymentMethod();

        if (!$method)
            return null;

        $type = $method->type;
        if ($type == 'link' || !isset($method->$type->wallet->type))
            return null;

        return $this->paymentMethodHelper->getPaymentMethodIcon($method->$type->wallet->type);
    }

    public function getCheckoutSession(): ?\Stripe\Checkout\Session
    {
        if ($this->checkoutSession)
            return $this->checkoutSession;

        try
        {
            $sessionId = $this->getInfo()->getAdditionalInformation("checkout_session_id");
            $checkoutSession = $this->paymentsConfig->getStripeClient()->checkout->sessions->retrieve($sessionId, [
                'expand' => [
                    'payment_intent',
                    'payment_intent.payment_method',
                    'setup_intent',
                    'setup_intent.payment_method',
                    'subscription',
                    'subscription.default_payment_method',
                    'subscription.latest_invoice.payment_intent'
                ]
            ]);

            return $this->checkoutSession = $checkoutSession;
        }
        catch (\Exception $e)
        {
            $this->helper->logInfo("Could not retrieve checkout session: " . $e->getMessage());
            return null;
        }
    }

    public function getPaymentIntent()
    {
        if (!empty($this->paymentIntent))
            return $this->paymentIntent;

        $transactionId = $this->getTransactionId();
        if ($transactionId && strpos($transactionId, "pi_") === 0)
        {
            return $this->paymentIntent = $this->hydratePaymentIntent($transactionId);
        }

        $checkoutSession = $this->getCheckoutSession();

        if (!empty($checkoutSession->payment_intent))
            return $this->paymentIntent = $this->hydratePaymentIntent($checkoutSession->payment_intent);

        if (!empty($checkoutSession->subscription->latest_invoice->payment_intent))
            return $this->paymentIntent = $this->hydratePaymentIntent($checkoutSession->subscription->latest_invoice->payment_intent);

        return null;
    }

    public function getSetupIntent()
    {
        if (!empty($this->setupIntent))
            return $this->setupIntent;

        $checkoutSession = $this->getCheckoutSession();

        if (!empty($checkoutSession->setup_intent))
        {
            return $this->setupIntent = $checkoutSession->setup_intent;
        }

        return null;
    }

    protected function hydratePaymentIntent($paymentIntent)
    {
        if (is_string($paymentIntent))
        {
            try
            {
                return $this->paymentsConfig->getStripeClient()->paymentIntents->retrieve($paymentIntent, ['expand' => ['payment_method']]);
            }
            catch (\Exception $e)
            {
                $this->helper->logInfo("Could not retrieve payment intent: " . $e->getMessage());
                return null;
            }
        }

        return $paymentIntent;
    }
    public function getPaymentStatus()
    {
        $checkoutSession = $this->getCheckoutSession();
        $paymentIntent = $this->getPaymentIntent();

        if (!empty($paymentIntent))
            return $this->getPaymentIntentStatus($paymentIntent);

        return "pending";
    }

    public function getPaymentStatusName()
    {
        $status = $this->getPaymentStatus();
        return ucfirst(str_replace("_", " ", $status));
    }

    public function getSubscriptionStatus()
    {
        $subscription = $this->getSubscription();

        if (empty($subscription))
            return null;

        return $subscription->status;
    }

    public function getSubscriptionStatusName()
    {
        $subscription = $this->getSubscription();

        if (empty($subscription))
            return null;

        if ($subscription->status == "trialing")
            return __("Trial ends %1", date("j M", $subscription->trial_end));

        return ucfirst($subscription->status);
    }

    public function getPaymentIntentStatus(?\Stripe\PaymentIntent $paymentIntent)
    {
        if (empty($paymentIntent->status))
            return null;

        switch ($paymentIntent->status)
        {
            case "requires_payment_method":
            case "requires_confirmation":
            case "requires_action":
            case "processing":
                return "pending";
            case "requires_capture":
                return "uncaptured";
            case "canceled":
                if (empty($paymentIntent->charges->data[0]))
                    return 'canceled';
                /** @var \Stripe\Charge $charge */
                $charge = $paymentIntent->charges->data[0];
                if (!empty($charge->failure_code))
                    return "failed";
                else
                    return "canceled";
            case "succeeded":
                if (!empty($paymentIntent->charges->data[0]->refunded))
                    return "refunded";
                else if (!empty($paymentIntent->charges->data[0]->amount_refunded))
                    return "partial_refund";
                else
                    return "succeeded";
            default:
                return $paymentIntent->status;
        }
    }

    public function getSubscription()
    {
        $checkoutSession = $this->getCheckoutSession();

        if (!empty($checkoutSession->subscription))
            return $checkoutSession->subscription;

        return null;
    }

    public function getCard()
    {
        $method = $this->getPaymentMethod();

        if (!empty($method->card))
            return $method->card;

        return null;
    }

    public function getRiskLevelCode()
    {
        $charge = $this->getCharge();

        if (isset($charge->outcome->risk_level))
            return $charge->outcome->risk_level;

        return '';
    }

    public function getRiskScore()
    {
        $charge = $this->getCharge();

        if (isset($charge->outcome->risk_score))
            return $charge->outcome->risk_score;

        return null;
    }

    public function getRiskEvaluation()
    {
        $risk = $this->getRiskLevelCode();
        return ucfirst(str_replace("_", " ", $risk));
    }

    public function isStripeMethod()
    {
        $method = $this->getMethod()->getMethod();

        if (strpos($method, "stripe_payments") !== 0 || $method == "stripe_payments_invoice")
            return false;

        return true;
    }

    public function getCharge()
    {
        $paymentIntent = $this->getPaymentIntent();

        if (!empty($paymentIntent->charges->data[0]))
            return $paymentIntent->charges->data[0];

        return null;
    }

    public function retrieveCharge($chargeId)
    {
        try
        {
            $token = $this->tokenHelper->cleanToken($chargeId);

            return $this->api->retrieveCharge($token);
        }
        catch (\Exception $e)
        {
            return null;
        }
    }

    public function getCustomerId()
    {
        $checkoutSession = $this->getCheckoutSession();

        if (isset($checkoutSession->customer) && !empty($checkoutSession->customer))
            return $checkoutSession->customer;

        return null;
    }

    public function getPaymentId()
    {
        $paymentIntent = $this->getPaymentIntent();

        if (isset($paymentIntent->id))
            return $paymentIntent->id;

        return null;
    }

    public function getTransactionId()
    {
        $transactionId = $this->getInfo()->getLastTransId();
        return $this->tokenHelper->cleanToken($transactionId);
    }

    public function getMode()
    {
        $checkoutSession = $this->getCheckoutSession();

        if ($checkoutSession && $checkoutSession->livemode)
            return "";

        return "test/";
    }

    public function getTitle()
    {
        $info = $this->getInfo();

        // Payment info block in admin area
        if ($info->getAdditionalInformation('payment_location'))
            return __($info->getAdditionalInformation('payment_location'));

        return $this->getMethod()->getTitle();
    }

    public function getVoucherLink()
    {
        $paymentIntent = $this->getPaymentIntent();

        if (!empty($paymentIntent->next_action->type))
        {
            $type = $paymentIntent->next_action->type;

            if (!empty($paymentIntent->next_action->$type->hosted_voucher_url))
                return $paymentIntent->next_action->$type->hosted_voucher_url;
        }

        return null;
    }

    public function getPaymentMethodVerificationUrl()
    {
        /** @var ?\Stripe\SetupIntent $setupIntent */
        $setupIntent = $this->getSetupIntent();

        if (!empty($setupIntent->next_action->type) && $setupIntent->next_action->type == "verify_with_microdeposits")
            return $setupIntent->next_action->verify_with_microdeposits->hosted_verification_url;

        return null;
    }

    public function isSetupIntent()
    {
        $transactionId = $this->getTransactionId();
        if (!empty($transactionId) && strpos($transactionId, "seti_") === 0)
            return true;

        return false;
    }

    public function isLegacyPaymentMethod()
    {
        $transactionId = $this->getTransactionId();
        if (!empty($transactionId) && (strpos($transactionId, "src_") !== false || strpos($transactionId, "ch_") !== false))
            return true;

        return false;
    }
}
