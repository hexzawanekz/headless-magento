<?php

namespace StripeIntegration\Payments\Model;

use Magento\Framework\Exception\LocalizedException;
use StripeIntegration\Payments\Exception\GenericException;

class CheckoutSession extends \Magento\Framework\Model\AbstractModel
{
    private ?\StripeIntegration\Payments\Model\Stripe\Checkout\Session $stripeCheckoutSession = null;
    private $stripeCheckoutSessionFactory;
    private \StripeIntegration\Payments\Helper\Subscriptions $subscriptionsHelper;
    private \StripeIntegration\Payments\Helper\Stripe\CheckoutSession $checkoutSessionHelper;
    private \StripeIntegration\Payments\Helper\Generic $paymentsHelper;
    private $localeHelper;
    private $stripeCouponFactory;
    private $customer;
    private $config;
    private $addressHelper;
    private $scopeConfig;
    private $stripeProductFactory;
    private $stripePriceFactory;
    private $compare;
    private $startDateFactory;
    private $quote = null;
    private $stripeCustomer;
    private $resourceModel;
    private $addressFactory;
    private $quoteHelper;
    private $orderHelper;
    private $checkoutFlow;
    private $convert;

    public function __construct(
        \StripeIntegration\Payments\Model\Stripe\Checkout\SessionFactory $stripeCheckoutSessionFactory,
        \StripeIntegration\Payments\Helper\Subscriptions $subscriptionsHelper,
        \StripeIntegration\Payments\Helper\Stripe\CheckoutSession $checkoutSessionHelper,
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Helper\Generic $paymentsHelper,
        \StripeIntegration\Payments\Helper\Locale $localeHelper,
        \StripeIntegration\Payments\Helper\Address $addressHelper,
        \StripeIntegration\Payments\Helper\Quote $quoteHelper,
        \StripeIntegration\Payments\Helper\Order $orderHelper,
        \StripeIntegration\Payments\Helper\Convert $convert,
        \StripeIntegration\Payments\Model\Stripe\CouponFactory $stripeCouponFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \StripeIntegration\Payments\Model\Stripe\ProductFactory $stripeProductFactory,
        \StripeIntegration\Payments\Model\Stripe\PriceFactory $stripePriceFactory,
        \StripeIntegration\Payments\Helper\Compare $compare,
        \StripeIntegration\Payments\Model\Subscription\StartDateFactory $startDateFactory,
        \StripeIntegration\Payments\Model\ResourceModel\CheckoutSession $resourceModel,
        \StripeIntegration\Payments\Model\Checkout\Flow $checkoutFlow,
        \Magento\Framework\Model\Context $context,
        \Magento\Customer\Model\AddressFactory $addressFactory,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->stripeCheckoutSessionFactory = $stripeCheckoutSessionFactory;
        $this->subscriptionsHelper = $subscriptionsHelper;
        $this->checkoutSessionHelper = $checkoutSessionHelper;
        $this->config = $config;
        $this->paymentsHelper = $paymentsHelper;
        $this->localeHelper = $localeHelper;
        $this->stripeCouponFactory = $stripeCouponFactory;
        $this->customer = $this->paymentsHelper->getCustomerModel();
        $this->addressHelper = $addressHelper;
        $this->scopeConfig = $scopeConfig;
        $this->stripeProductFactory = $stripeProductFactory;
        $this->stripePriceFactory = $stripePriceFactory;
        $this->compare = $compare;
        $this->startDateFactory = $startDateFactory;
        $this->resourceModel = $resourceModel;
        $this->addressFactory = $addressFactory;
        $this->quoteHelper = $quoteHelper;
        $this->orderHelper = $orderHelper;
        $this->checkoutFlow = $checkoutFlow;
        $this->convert = $convert;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    protected function _construct()
    {
        $this->_init('StripeIntegration\Payments\Model\ResourceModel\CheckoutSession');
    }

    public function fromQuote($quote): CheckoutSession
    {
        try
        {
            if ($this->checkoutFlow->shouldNotBillTrialSubscriptionItems())
            {
                throw new GenericException("Cannot create a Checkout Session from a quote while the order is being placed.");
            }

            $this->quote = $quote;

            if (empty($quote) || empty($quote->getId()))
                return $this;

            $this->resourceModel->load($this, $quote->getId(), 'quote_id');

            if ($this->getCheckoutSessionId())
            {
                $this->stripeCheckoutSession  = $this->stripeCheckoutSessionFactory->create()->load($this->getCheckoutSessionId());
            }

            return $this;
        }
        catch (\Exception $e)
        {
            $this->paymentsHelper->logError($e->getMessage(), $e->getTraceAsString());
            $this->setData([]);
            return $this;
        }
    }

    // Creates a session if one does not exist
    public function fromOrder($order, $cancelOldOrder = false)
    {
        $this->quote = null;

        if (empty($order))
        {
            throw new GenericException('Invalid Stripe Checkout order.');
        }

        $this->resourceModel->load($this, $order->getQuoteId(), 'quote_id');

        $this->quote = $this->quoteHelper->loadQuoteById($order->getQuoteId());

        if (empty($this->quote) || empty($this->quote->getId()))
        {
            throw new GenericException('Could not find quote for order.');
        }

        if ($this->getCheckoutSessionId())
        {
            $this->stripeCheckoutSession  = $this->stripeCheckoutSessionFactory->create()->load($this->getCheckoutSessionId());
        }
        else
        {
            $this->stripeCheckoutSession = $this->stripeCheckoutSessionFactory->create();
        }

        if ($cancelOldOrder && $this->getOrderIncrementId() && $this->getOrderIncrementId() != $order->getIncrementId() && !$this->isComplete())
        {
            $oldOrder = $this->orderHelper->loadOrderByIncrementId($this->getOrderIncrementId());
            $comment = __("The cart contents or customer details have changed. The order is canceled because a new one will be placed (#%1) with the new details.", $order->getIncrementId());
            $oldOrder->addStatusToHistory($status = false, $comment, $isCustomerNotified = false);
            $this->paymentsHelper->cancelOrCloseOrder($oldOrder);
        }

        $params = $this->getParamsFromOrder($order);
        $stripeCheckoutSessionObject = $this->stripeCheckoutSession->getStripeObject();

        if (!$stripeCheckoutSessionObject)
        {
            $this->stripeCheckoutSession->fromParams($params);
            $stripeCheckoutSessionObject = $this->stripeCheckoutSession->getStripeObject();
        }
        else if (
            $this->hasChanged($params) ||
            $this->needsUpdate($params) ||
            $this->hasExpired() ||
            $this->isComplete() ||
            empty($stripeCheckoutSessionObject->payment_intent) // Case for subscriptions
        )
        {
            $this->cancelCheckoutSession();
            $this->stripeCheckoutSession->fromParams($params);
            $stripeCheckoutSessionObject = $this->stripeCheckoutSession->getStripeObject();
        }
        else if (!empty($params["payment_intent_data"]) && !empty($stripeCheckoutSessionObject->payment_intent))
        {
            /** @var \Stripe\PaymentIntent $paymentIntent */
            $paymentIntent = $stripeCheckoutSessionObject->payment_intent;
            $updateParams = $this->checkoutSessionHelper->getPaymentIntentUpdateParams($params["payment_intent_data"], $paymentIntent);
            if (!empty($paymentIntent->id))
                $this->config->getStripeClient()->paymentIntents->update($paymentIntent->id, $updateParams);
        }

        $this->setQuoteId($this->quote->getId());
        $this->setOrderIncrementId($order->getIncrementId());
        $this->setCheckoutSessionId($this->stripeCheckoutSession->getId());
        $this->resourceModel->save($this);

        return $this;
    }

    public function createFromQuote($quote)
    {
        $params = $this->getParamsFromQuote($quote);
        if (!empty($params["payment_intent_data"])) // In subscription mode, this is not set
            $params["payment_intent_data"]["description"] = $this->quoteHelper->getQuoteDescription($quote);

        $this->stripeCheckoutSession = $this->stripeCheckoutSessionFactory->create();
        $checkoutSession = $this->stripeCheckoutSession->fromParams($params)->getStripeObject();
        $this->setCheckoutSessionId($checkoutSession->id)
            ->setQuoteId($quote->getId())
            ->save();
    }

    private function createDummyCheckoutSessionFrom($quote)
    {
        $params = $this->getParamsFromQuote($quote);
        $stripeCheckoutSession = $this->stripeCheckoutSessionFactory->create();
        $checkoutSession = $stripeCheckoutSession->fromParams($params)->getStripeObject();
        return $checkoutSession;
    }

    public function getAvailablePaymentMethods($quote)
    {
        $methods = [];

        if (empty($quote) || empty($quote->getId()))
            return $methods;

        $checkoutSession = $this->createDummyCheckoutSessionFrom($quote);

        if (!empty($checkoutSession->payment_method_types))
            $methods = $checkoutSession->payment_method_types;

        return $methods;
    }

    public function updateCustomerEmail($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        {
            // The email is invalid
            return;
        }

        if (!$this->config->isEnabled() || !$this->config->isRedirectPaymentFlow())
        {
            return;
        }

        if ($this->paymentsHelper->isCustomerLoggedIn())
        {
            // No need to update logged in customers
            return;
        }

        $quote = $this->quoteHelper->getQuote();

        $this->resourceModel->load($this, $quote->getId(), 'quote_id');

        if (!$this->getCheckoutSessionId() || !$this->stripeCheckoutSession)
        {
            return;
        }

        /** @var \Stripe\Checkout\Session $checkoutSession */
        $checkoutSession = $this->stripeCheckoutSession->load($this->getCheckoutSessionId())->getStripeObject();

        if ($this->isComplete() || $this->hasExpired())
        {
            return;
        }

        if (!empty($checkoutSession->customer_details->email) && $email != $checkoutSession->customer_details->email)
        {
            if ($checkoutSession->customer)
            {
                $this->config->getStripeClient()->customers->update($checkoutSession->customer, [
                    'email' => $email
                ]);
            }
            else
            {
                // $this->cancelCheckoutSession();
                $quote->setCustomerEmail($email);
                $this->quoteHelper->saveQuote($quote);
                // $this->createFromQuote($quote);
            }
        }
    }

    public function getStripeObject()
    {
        if (empty($this->stripeCheckoutSession))
            return null;

        return $this->stripeCheckoutSession->getStripeObject();
    }

    public function getParamsFromOrder($order)
    {
        $subscription = $this->subscriptionsHelper->getSubscriptionFromOrder($order);

        $grandTotal = $order->getGrandTotal();
        if (!empty($subscription['profile']['deducted_order_amount']))
            $grandTotal += $subscription['profile']['deducted_order_amount'];

        $lineItems = $this->getLineItems($subscription, $grandTotal, $order->getOrderCurrencyCode());
        $quote = $this->quoteHelper->loadQuoteById($order->getQuoteId());
        $params = $this->getParamsFrom($lineItems, $subscription, $quote, $order);

        return $params;
    }

    public function getParamsFromQuote($quote)
    {
        if (empty($quote))
            throw new GenericException("No quote specified for Checkout params.");

        $subscription = $this->subscriptionsHelper->getSubscriptionFromQuote($quote);
        $lineItems = $this->getLineItems($subscription, $quote->getGrandTotal(), $quote->getQuoteCurrencyCode());
        $params = $this->getParamsFrom($lineItems, $subscription, $quote);

        if ($params["mode"] == 'payment') {
            $stripeCheckoutOnSession = \StripeIntegration\Payments\Helper\PaymentMethod::STRIPE_CHECKOUT_ON_SESSION_PM;
            $value = ['setup_future_usage' => 'on_session'];
            foreach ($stripeCheckoutOnSession as $code)
            {
                $params["payment_method_options"][$code] = $value;
            }

            $stripeCheckoutOffSession = \StripeIntegration\Payments\Helper\PaymentMethod::STRIPE_CHECKOUT_OFF_SESSION_PM;
            $value = ['setup_future_usage' => 'off_session'];
            foreach ($stripeCheckoutOffSession as $code)
            {
                $params["payment_method_options"][$code] = $value;
            }

            $stripeCheckoutNoneSession = \StripeIntegration\Payments\Helper\PaymentMethod::STRIPE_CHECKOUT_NONE_PM;
            $value = ['setup_future_usage' => 'none'];
            foreach ($stripeCheckoutNoneSession as $code)
            {
                $params["payment_method_options"][$code] = $value;
            }
        }

        return $params;
    }

    protected function getLineItems($subscription, $grandTotal, $currencyCode)
    {
        $currency = strtolower($currencyCode);
        $lines = [];

        if (empty($subscription['profile']))
        {
            $oneTimePayment = $this->getOneTimePayment($grandTotal, $currency);
            if ($oneTimePayment)
                $lines[] = $oneTimePayment;
        }
        else
        {
            $profile = $subscription['profile'];
            $subscriptionsProductIDs[] = $subscription['product']->getId();
            $interval = $profile['interval'];
            $intervalCount = $profile['interval_count'];

            $subscriptionTotal = $this->subscriptionsHelper->getSubscriptionTotalFromProfile($profile);
            $currencyPrecision = $this->convert->getCurrencyPrecision($currency);
            $subscriptionTotal = round(floatval($subscriptionTotal), $currencyPrecision);

            $remainingAmount = $grandTotal - $subscriptionTotal;

            $oneTimePayment = $this->getOneTimePayment($remainingAmount, $currency, true);
            if ($oneTimePayment)
                $lines[] = $oneTimePayment;

            $recurringPayment = $this->getRecurringPayment($subscription, $subscriptionsProductIDs, $subscriptionTotal, $currency, $interval, $intervalCount);
            if ($recurringPayment)
                $lines[] = $recurringPayment;
        }

        return $lines;
    }

    protected function getParamsFrom($lineItems, $subscription, $quote, $order = null)
    {
        $returnUrl = $this->paymentsHelper->getUrl('stripe/payment/index', ["payment_method" => "stripe_checkout"]);
        $cancelUrl = $this->paymentsHelper->getUrl('stripe/payment/cancel', ["payment_method" => "stripe_checkout"]);

        $params = [
            'expires_at' => $this->getExpirationTime(),
            'cancel_url' => $cancelUrl,
            'success_url' => $returnUrl,
            'locale' => $this->localeHelper->getStripeCheckoutLocale()
        ];

        if (!empty($subscription))
        {
            $params["mode"] = "subscription";
            $params["line_items"] = $lineItems;
            $params["subscription_data"] = [
                "metadata" => $this->subscriptionsHelper->collectMetadataForSubscription($subscription['profile'])
            ];

            $profile = $subscription['profile'];

            if ($profile['expiring_coupon'])
            {
                $coupon = $this->stripeCouponFactory->create()->fromSubscriptionProfile($profile);
                if ($coupon->getId())
                {
                    $params['discounts'][] = ['coupon' => $coupon->getId()];
                }
            }

            $startDateModel = $this->startDateFactory->create()->fromProfile($profile);
            $hasOneTimePayment = false;
            foreach($lineItems as $lineItem)
            {
                if ($this->isOneTimePayment($lineItem))
                {
                    $hasOneTimePayment = true;
                    break;
                }
            }
            if ($startDateModel->isCompatibleWithTrials($hasOneTimePayment))
            {
                if ($profile['trial_end'])
                {
                    $params['subscription_data']['trial_period_days'] = $startDateModel->getDaysUntilStartDate($profile['trial_end']);
                }
                else if ($profile['trial_days'])
                {
                    $params['subscription_data']['trial_period_days'] = $profile['trial_days'];
                }
            }

            $startDateParams = $startDateModel->getParams($hasOneTimePayment, true);
            if (!empty($startDateParams))
            {
                $params['subscription_data'] = array_merge_recursive($params['subscription_data'], $startDateParams);
            }

            $params["payment_method_options"] = $this->getPaymentMethodOptions();
        }
        else if ($this->config->getPaymentAction() == "order")
        {
            $params['mode'] = 'setup';
            $params['payment_method_types'] = ['card'];
        }
        else
        {
            $params["mode"] = "payment";
            $params["line_items"] = $lineItems;
            $params["payment_intent_data"] = $this->getPaymentIntentData($quote, $order);
            $params["submit_type"] = "pay";
            $params["payment_method_options"] = $this->getPaymentMethodOptions();
        }

        if ($this->config->alwaysSaveCards())
        {
            try
            {
                $this->customer->createStripeCustomerIfNotExists(false, $order);
                $this->stripeCustomer = $this->customer->retrieveByStripeID();
                if (!empty($this->stripeCustomer->id))
                    $params['customer'] = $this->stripeCustomer->id;
            }
            catch (\Stripe\Exception\CardException $e)
            {
                throw new LocalizedException(__($e->getMessage()));
            }
            catch (\Exception $e)
            {
                $this->paymentsHelper->throwError(__('An error has occurred. Please contact us to complete your order.'), $e);
            }
        }
        else
        {
            if ($this->paymentsHelper->isCustomerLoggedIn())
                $this->customer->createStripeCustomerIfNotExists(false, $order);

            $this->stripeCustomer = $this->customer->retrieveByStripeID();
            if (!empty($this->stripeCustomer->id))
                $params['customer'] = $this->stripeCustomer->id;
            else if ($order)
                $params['customer_email'] = $order->getCustomerEmail();
            else if ($quote->getCustomerEmail())
                $params['customer_email'] = $quote->getCustomerEmail();
        }

        return $params;
    }

    protected function getPaymentMethodOptions()
    {
        return [
            "acss_debit" => [
                "mandate_options" => [
                    "payment_schedule" => "sporadic",
                    "transaction_type" => "personal"
                ]
            ],
            // "bacs_debit" => [
            //     "setup_future_usage" => "off_session"
            // ]
        ];
    }

    protected function getExpirationTime()
    {
        $storeId = $this->paymentsHelper->getStoreId();
        $cookieLifetime = $this->scopeConfig->getValue("web/cookie/cookie_lifetime", \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        $oneHour = 1 * 60 * 60;
        $twentyFourHours = 24 * 60 * 60;
        $cookieLifetime = max($oneHour, $cookieLifetime);
        $cookieLifetime = min($twentyFourHours, $cookieLifetime);
        $timeDifference = $this->paymentsHelper->getStripeApiTimeDifference();

        return time() + $cookieLifetime + $timeDifference;
    }


    protected function getOneTimePayment($oneTimeAmount, $currency, $isUsedWithSubscription = false)
    {
        if ($oneTimeAmount > 0)
        {
            if ($isUsedWithSubscription)
            {
                $productId = "one_time_payment";
                $name = __("One time payment");
            }
            else
            {
                $productId = "amount_due";
                $name = __("Amount due");
            }

            $metadata = [
                'Type' => 'RegularProductsTotal',
            ];

            $stripeAmount = $this->paymentsHelper->convertMagentoAmountToStripeAmount($oneTimeAmount, $currency);

            $stripeProductModel = $this->stripeProductFactory->create()->fromData($productId, $name, $metadata);
            $stripePriceModel = $this->stripePriceFactory->create()->fromData($stripeProductModel->getId(), $stripeAmount, $currency);

            $lineItem = [
                'price' => $stripePriceModel->getId(),
                'quantity' => 1,
            ];

            return $lineItem;
        }

        return null;
    }

    protected function getRecurringPayment($subscription, $subscriptionsProductIDs, $allSubscriptionsTotal, $currency, $interval, $intervalCount)
    {
        if (!empty($subscription['profile']))
        {
            $profile = $subscription['profile'];

            $interval = $profile['interval'];
            $intervalCount = $profile['interval_count'];
            $currency = $profile['currency'];
            $magentoAmount = $this->subscriptionsHelper->getSubscriptionTotalWithDiscountAdjustmentFromProfile($profile);
            $stripeAmount = $this->paymentsHelper->convertMagentoAmountToStripeAmount($magentoAmount, $currency);

            if (!empty($subscription['quote_item']))
            {
                $stripeProductModel = $this->stripeProductFactory->create()->fromQuoteItem($subscription['quote_item']);
            }
            else if (!empty($subscription['order_item']))
            {
                $stripeProductModel = $this->stripeProductFactory->create()->fromOrderItem($subscription['order_item']);
            }
            else
            {
                throw new LocalizedException(__("Could not create subscription product in Stripe."));
            }

            $stripePriceModel = $this->stripePriceFactory->create()->fromData($stripeProductModel->getId(), $stripeAmount, $currency, $interval, $intervalCount);

            $lineItem = [
                'price' => $stripePriceModel->getId(),
                'quantity' => 1,

            ];

            return $lineItem;
        }

        return null;
    }

    private function getPaymentIntentData($quote, $order = null)
    {
        $params = [
            'capture_method' => $this->config->getCaptureMethod()
        ];

        if ($order)
        {
            $params["metadata"] = $this->config->getMetadata($order);
            $params['description'] = $this->orderHelper->getOrderDescription($order);
        }
        else
        {
            $params['description'] = $this->quoteHelper->getQuoteDescription($quote);
        }

        $futureUsage = $this->config->getSetupFutureUsage($quote);
        if ($futureUsage)
        {
            $params['setup_future_usage'] = $futureUsage;
        }

        $statementDescriptor = $this->config->getStatementDescriptor();
        if (!empty($statementDescriptor))
        {
            $params["statement_descriptor"] = $statementDescriptor;
        }

        $shipping = $this->getShippingAddressFrom($quote, $order);
        if ($shipping)
        {
            $params['shipping'] = $shipping;
        }

        $customerEmail = $quote->getCustomerEmail();
        if ($customerEmail && $this->config->isReceiptEmailsEnabled())
        {
            $params["receipt_email"] = $customerEmail;
        }

        return $params;
    }

    public function getShippingAddressFrom($quote, $order = null)
    {
        if ($order)
            $obj = $order;
        else if ($quote)
            $obj = $quote;
        else
            throw new GenericException("No quote or order specified");

        if (!$obj || $obj->getIsVirtual())
            return null;

        $address = $obj->getShippingAddress();

        if (empty($address))
            return null;

        if (empty($address->getFirstname()))
            $address = $this->addressFactory->create()->load($address->getAddressId());

        if (empty($address->getFirstname()))
            return null;

        return $this->addressHelper->getStripeShippingAddressFromMagentoAddress($address);
    }

    // Compares parameters which may affect which payment methods will be available at the Stripe Checkout landing page
    public function hasChanged($params)
    {
        $checkoutSession = $this->stripeCheckoutSession->getStripeObject();

        if (empty($checkoutSession))
        {
            throw new GenericException("No Stripe Checkout session found.");
        }

        if ($params["mode"] == "subscription")
        {
            $comparisonParams = [
                "payment_intent" => "unset",
                "mode" => $params["mode"]
            ];
        }
        else if ($params["mode"] == "setup")
        {
            $comparisonParams = [
                "payment_intent" => "unset",
                "mode" => $params["mode"]
            ];
        }
        else
        {
            $comparisonParams = [
                "submit_type" => $params["submit_type"]
            ];

            if (!empty($params["payment_intent_data"]["capture_method"]))
                $comparisonParams["payment_intent"]["capture_method"] = $params["payment_intent_data"]["capture_method"];
            // else
                // is set as automatic or whatever the configured default is

            // Shipping country may affect payment methods
            if (!empty($params["payment_intent_data"]["shipping"]["address"]["country"]))
                $comparisonParams["payment_intent"]["shipping"]["address"]["country"] = $params["payment_intent_data"]["shipping"]["address"]["country"];
            else
                $comparisonParams["payment_intent"]["shipping"] = "unset";

            // Save customer card may affect payment methods
            if (!empty($params["payment_intent_data"]["setup_future_usage"]))
                $comparisonParams["payment_intent"]["setup_future_usage"] = $params["payment_intent_data"]["setup_future_usage"];
            else
                $comparisonParams["payment_intent"]["setup_future_usage"] = "unset";

            // Customer does not affect which payment methods are available, but it may do in the future based on Radar risk level or customer credit score
            if (!empty($params["customer"]))
                $comparisonParams["customer"] = $params["customer"];
        }

        if (!empty($params['subscription_data']))
        {
            if (!empty($checkoutSession->subscription))
            {
                throw new GenericException("Subscription data is not supported in this version.");
            }
        }

        if ($this->compare->isDifferent($checkoutSession, $comparisonParams))
        {
            return true;
        }

        if ($params['mode'] != "setup")
        {
            $lineItems = $this->config->getStripeClient()->checkout->sessions->allLineItems($checkoutSession->id, ['limit' => 100]);
            if (count($lineItems->data) != count($params['line_items']))
            {
                return true;
            }

            $comparisonParams = [];
            foreach ($lineItems->data as $i => $item)
            {
                $comparisonParams[$i] = [
                    'price' => [
                        'id' => $params['line_items'][$i]['price']
                    ],
                    'quantity' => $params['line_items'][$i]['quantity']
                ];

                /** @var \Stripe\Price $price */
                $price = $this->stripePriceFactory->create()->load($params['line_items'][$i]['price'])->getStripeObject();

                if (empty($price->recurring))
                    $comparisonParams[$i]['price']['recurring'] = "unset";
                else
                {
                    $comparisonParams[$i]['price']['recurring']['interval'] = $price->recurring->interval;
                    $comparisonParams[$i]['price']['recurring']['interval_count'] = $price->recurring->interval_count;
                }
            }

            if ($this->compare->isDifferent($lineItems->data, $comparisonParams))
            {
                return true;
            }
        }

        return false;
    }

    public function getOrder()
    {
        $orderIncrementId = $this->getOrderIncrementId();

        if (empty($orderIncrementId))
            return null;

        $order = $this->orderHelper->loadOrderByIncrementId($orderIncrementId);
        if ($order && $order->getId())
            return $order;

        return null;
    }

    public function hasExpired()
    {
        if (!$this->getCheckoutSessionId() || !$this->stripeCheckoutSession)
            return false;

        $checkoutSession = $this->stripeCheckoutSession->getStripeObject();

        return ($checkoutSession && $checkoutSession->status == "expired");
    }

    public function isComplete()
    {
        if (!$this->getCheckoutSessionId() || !$this->stripeCheckoutSession)
            return false;

        $checkoutSession = $this->stripeCheckoutSession->getStripeObject();

        return ($checkoutSession && $checkoutSession->status == "complete");
    }

    public function cancelOrder($orderComment)
    {
        if (!$this->getOrderIncrementId())
            return;

        $order = $this->orderHelper->loadOrderByIncrementId($this->getOrderIncrementId());
        if (!$order || !$order->getId())
            return;

        $state = \Magento\Sales\Model\Order::STATE_CANCELED;
        $status = $order->getConfig()->getStateDefaultStatus($state);
        $order->addStatusToHistory($status, $orderComment, $isCustomerNotified = false);
        $this->orderHelper->saveOrder($order);

        $this->setOrderIncrementId(null)->save();
    }

    public function cancelCheckoutSession()
    {
        $checkoutSession = $this->stripeCheckoutSession->getStripeObject();

        if (empty($checkoutSession))
        {
            return;
        }

        try
        {
            if ($this->canCancel())
            {
                $this->config->getStripeClient()->checkout->sessions->expire($checkoutSession->id, []);
                $this->setCheckoutSessionId(null)->save();
            }
        }
        catch (\Exception $e)
        {
            $this->paymentsHelper->logError("Cannot cancel checkout session: " . $e->getMessage());
        }
    }

    protected function canCancel()
    {
        $checkoutSession = $this->stripeCheckoutSession->getStripeObject();

        if (empty($checkoutSession))
        {
            return false;
        }

        if (in_array($checkoutSession->status, ["expired", "complete"]))
            return false;

        return true;
    }

    protected function isOneTimePayment($lineItem)
    {
        if (!empty($lineItem['price']['recurring']))
            return false;

        if (!empty($lineItem['price_data']['recurring']))
            return false;

        if (is_string($lineItem['price']))
        {
            $price = $this->config->getStripeClient()->prices->retrieve($lineItem['price'], []);
            if (!empty($price['recurring']))
                return false;
        }

        return true;
    }

    // Checks if the customer email has changed
    public function needsUpdate($params)
    {
        $checkoutSession = $this->stripeCheckoutSession->getStripeObject();

        if (empty($checkoutSession->customer_details->email)
            && !empty($params['customer_email']))
        {
            return true;
        }

        if (!empty($checkoutSession->customer_details->email)
            && !empty($params['customer_email'])
            && $checkoutSession->customer_details->email != $params['customer_email'])
        {
            return true;
        }

        return false;
    }
}
