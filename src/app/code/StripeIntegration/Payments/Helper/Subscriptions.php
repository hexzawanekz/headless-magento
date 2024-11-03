<?php

declare(strict_types=1);

namespace StripeIntegration\Payments\Helper;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use StripeIntegration\Payments\Exception\CacheInvalidationException;
use Magento\Framework\Exception\LocalizedException;
use StripeIntegration\Payments\Exception\GenericException;
use StripeIntegration\Payments\Exception\InvalidSubscriptionProduct;

class Subscriptions
{
    public $couponCodes = [];
    public $coupons = [];
    public $subscriptions = [];
    public $invoices = [];
    public $paymentIntents = [];
    public $trialingSubscriptionsAmounts = null;
    public $shippingTaxPercent = null;

    private $localCache = [];
    private $addressHelper;
    private $subscriptionProductFactory;
    private $paymentIntentModelFactory;
    private $stripeSubscriptionFactory;
    private $stripeProductFactory;
    private $stripePriceFactory;
    private $stripeCouponFactory;
    private $subscriptionCollectionFactory;
    private $priceCurrency;
    private $customer;
    private $subscriptionFactory;
    private $recurringOrderFactory;
    private $compare;
    private $paymentIntentHelper;
    private $taxHelper;
    private $config;
    private $paymentsHelper;
    private $subscriptionOptionsFactory;
    private $startDateFactory;
    private $subscriptionScheduleFactory;
    private $quoteHelper;
    private $checkoutSessionHelper;
    private $orderHelper;
    private $checkoutFlow;
    private $convert;
    private $paymentMethodTypesHelper;
    private $initialFeeHelper;
    private $subscriptionCartFactory;
    private $productHelper;

    public function __construct(
        \StripeIntegration\Payments\Helper\Generic $paymentsHelper,
        \StripeIntegration\Payments\Helper\Compare $compare,
        \StripeIntegration\Payments\Helper\Address $addressHelper,
        \StripeIntegration\Payments\Helper\PaymentIntent $paymentIntentHelper,
        \StripeIntegration\Payments\Helper\Quote $quoteHelper,
        \StripeIntegration\Payments\Helper\Order $orderHelper,
        \StripeIntegration\Payments\Helper\Convert $convert,
        \StripeIntegration\Payments\Helper\PaymentMethodTypes $paymentMethodTypesHelper,
        \StripeIntegration\Payments\Helper\InitialFee $initialFeeHelper,
        \StripeIntegration\Payments\Helper\Product $productHelper,
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Model\SubscriptionProductFactory $subscriptionProductFactory,
        \StripeIntegration\Payments\Model\PaymentIntentFactory $paymentIntentModelFactory,
        \StripeIntegration\Payments\Model\Stripe\SubscriptionFactory $stripeSubscriptionFactory,
        \StripeIntegration\Payments\Model\Stripe\ProductFactory $stripeProductFactory,
        \StripeIntegration\Payments\Model\Stripe\PriceFactory $stripePriceFactory,
        \StripeIntegration\Payments\Model\Stripe\CouponFactory $stripeCouponFactory,
        \StripeIntegration\Payments\Model\ResourceModel\Subscription\CollectionFactory $subscriptionCollectionFactory,
        \StripeIntegration\Payments\Model\Checkout\Flow $checkoutFlow,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        \StripeIntegration\Payments\Model\SubscriptionFactory $subscriptionFactory,
        \StripeIntegration\Payments\Helper\TaxHelper $taxHelper,
        \StripeIntegration\Payments\Helper\RecurringOrderFactory $recurringOrderFactory,
        \StripeIntegration\Payments\Model\SubscriptionOptionsFactory $subscriptionOptionsFactory,
        \StripeIntegration\Payments\Model\Subscription\StartDateFactory $startDateFactory,
        \StripeIntegration\Payments\Model\Subscription\ScheduleFactory $subscriptionScheduleFactory,
        \StripeIntegration\Payments\Model\Subscription\CartFactory $subscriptionCartFactory,
        \StripeIntegration\Payments\Helper\CheckoutSession $checkoutSessionHelper
    ) {
        $this->paymentsHelper = $paymentsHelper;
        $this->compare = $compare;
        $this->addressHelper = $addressHelper;
        $this->paymentIntentHelper = $paymentIntentHelper;
        $this->quoteHelper = $quoteHelper;
        $this->orderHelper = $orderHelper;
        $this->convert = $convert;
        $this->config = $config;
        $this->paymentMethodTypesHelper = $paymentMethodTypesHelper;
        $this->subscriptionProductFactory = $subscriptionProductFactory;
        $this->paymentIntentModelFactory = $paymentIntentModelFactory;
        $this->stripeSubscriptionFactory = $stripeSubscriptionFactory;
        $this->stripeProductFactory = $stripeProductFactory;
        $this->stripePriceFactory = $stripePriceFactory;
        $this->stripeCouponFactory = $stripeCouponFactory;
        $this->subscriptionCollectionFactory = $subscriptionCollectionFactory;
        $this->checkoutFlow = $checkoutFlow;
        $this->priceCurrency = $priceCurrency;
        $this->customer = $paymentsHelper->getCustomerModel();
        $this->subscriptionFactory = $subscriptionFactory;
        $this->taxHelper = $taxHelper;
        $this->recurringOrderFactory = $recurringOrderFactory;
        $this->subscriptionOptionsFactory = $subscriptionOptionsFactory;
        $this->startDateFactory = $startDateFactory;
        $this->subscriptionScheduleFactory = $subscriptionScheduleFactory;
        $this->checkoutSessionHelper = $checkoutSessionHelper;
        $this->initialFeeHelper = $initialFeeHelper;
        $this->productHelper = $productHelper;
        $this->subscriptionCartFactory = $subscriptionCartFactory;
    }

    public function getSubscriptionExpandParams()
    {
        return ['latest_invoice.payment_intent', 'pending_setup_intent'];
    }

    public function getSubscriptionParamsFromOrder($order, $paymentIntentParams)
    {
        if (!$this->config->isSubscriptionsEnabled())
            return null;

        $subscription = $this->getSubscriptionFromOrder($order);
        $profile = $subscription['profile'];
        $subscriptionItems = $this->getSubscriptionItemsFromSubscriptionDetails($subscription);

        if (empty($subscriptionItems))
            return null;

        $stripeCustomer = $this->customer->createStripeCustomerIfNotExists();
        $this->customer->save();

        if (!$stripeCustomer)
            throw new GenericException("Could not create customer in Stripe.");

        $metadata = $subscriptionItems[0]['metadata']; // There is only one item for the entire order

        $params = [
            'description' => $this->orderHelper->getOrderDescription($order),
            'customer' => $stripeCustomer->id,
            'items' => $subscriptionItems,
            'expand' => $this->getSubscriptionExpandParams(),
            'metadata' => $metadata,
            'payment_behavior' => 'default_incomplete',
            'payment_settings' => [
                'save_default_payment_method' => 'on_subscription'
            ]
        ];

        $isExpressCheckout = $order && $order->getPayment()->getAdditionalInformation("confirmation_token") && $this->checkoutFlow->isExpressCheckout;
        if ($isExpressCheckout)
        {
            $paymentMethodTypes = $this->paymentMethodTypesHelper->getPaymentMethodTypes(true);
            $params['payment_settings']['payment_method_types'] = $paymentMethodTypes;
        }

        if ($paymentIntentParams['amount'] > 0)
        {
            $stripeDiscountAdjustment = $this->getStripeDiscountAdjustment($subscription);
            $normalPrice = $this->createPriceForOneTimePayment($paymentIntentParams['amount'] + $stripeDiscountAdjustment, $paymentIntentParams['currency']);
            $params['add_invoice_items'] = [[
                "price" => $normalPrice->id,
                "quantity" => 1
            ]];
        }

        $hasOneTimePayment = !empty($params['add_invoice_items']);
        $startDateModel = $this->startDateFactory->create()->fromProfile($profile);

        if (!empty($paymentIntentParams['payment_method']) && ($startDateModel->isValid()))
        {
            $params['default_payment_method'] = $paymentIntentParams['payment_method'];
        }
        else if ($this->checkoutSessionHelper->isSubscriptionReactivate())
        {
            $subscriptionReactivateDetails = $this->checkoutSessionHelper->getSubscriptionReactivateDetails();
            $paymentMethodId = $this->orderHelper->getPaymentMethodId($order);
            if (isset($subscriptionReactivateDetails['subscription_data']['default_payment_method']))
            {
                $params['default_payment_method'] = $subscriptionReactivateDetails['subscription_data']['default_payment_method'];
            }
            else if ($paymentMethodId)
            {
                $params['default_payment_method'] = $paymentMethodId;
            }
        }

        if (!empty($profile['expiring_coupon']))
        {
            $coupon = $this->stripeCouponFactory->create()->fromSubscriptionProfile($profile);
            if ($coupon->getId())
                $params['coupon'] = $coupon->getId();
        }

        $startDateModel = $this->startDateFactory->create()->fromProfile($profile);
        $hasOneTimePayment = !empty($params['add_invoice_items']);
        if ($startDateModel->isCompatibleWithTrials($hasOneTimePayment))
        {
            if ($profile['trial_end'])
            {
                $params['trial_end'] = $profile['trial_end'];
            }
            else if ($profile['trial_days'])
            {
                $params['trial_period_days'] = $profile['trial_days'];
            }
        }

        return $params;
    }

    public function filterToUpdateableParams($params)
    {
        $updateParams = [];

        if (empty($params))
            return $updateParams;

        $updateable = ['metadata', 'trial_end', 'description', 'default_payment_method'];

        foreach ($params as $key => $value)
        {
            if (in_array($key, $updateable))
                $updateParams[$key] = $value;
        }

        return $updateParams;
    }

    public function invalidateSubscription($subscription, $params)
    {
        $subscriptionItems = [];

        foreach ($params["items"] as $item)
        {
            $subscriptionItems[] = [
                "metadata" => [
                    "Type" => $item["metadata"]["Type"],
                    "SubscriptionProductIDs" => $item["metadata"]["SubscriptionProductIDs"]
                ],
                "price" => [
                    "id" => $item["price"]
                ],
                "quantity" => $item["quantity"]
            ];
        }

        $expectedValues = [
            "customer" => $params["customer"],
            "items" => [
                "data" => $subscriptionItems
            ]
        ];

        if (!empty($params['add_invoice_items']))
        {
            $oneTimeAmount = "unset";
            foreach ($params['add_invoice_items'] as $item)
            {
                $oneTimeAmount = [
                    "price" => [
                        "id" => $item["price"]
                    ],
                    "quantity" => $item["quantity"]
                ];
            }

            if (empty($subscription->latest_invoice->lines->data))
                throw new CacheInvalidationException("Non-updateable subscription details have changed: Regular items were added to the cart.");

            $hasRegularItems = false;
            foreach ($subscription->latest_invoice->lines->data as $invoiceLineItem)
            {
                if (!empty($invoiceLineItem->price->recurring->interval))
                    continue; // This is a subscription item

                $hasRegularItems = true;

                if ($this->compare->isDifferent($invoiceLineItem, $oneTimeAmount))
                {
                    throw new CacheInvalidationException("Non-updateable subscription details have changed: One time payment amount has changed.");
                }
            }

            if (!$hasRegularItems && $oneTimeAmount !== "unset")
                throw new CacheInvalidationException("Non-updateable subscription details have changed: Regular items were added to the cart.");
        }
        else
        {
            if (!empty($subscription->latest_invoice->lines->data))
            {
                foreach ($subscription->latest_invoice->lines->data as $invoiceLineItem)
                {
                    if (empty($invoiceLineItem->price->recurring->interval))
                        throw new CacheInvalidationException("Non-updateable subscription details have changed: Regular items were removed from the cart.");
                }
            }
        }

        if (!empty($subscription->latest_invoice))
        {
            if (!empty($params['coupon']))
            {
                $expectedValues['latest_invoice']['discount']['coupon']['id'] = $params['coupon'];
            }
            else
            {
                $expectedValues['latest_invoice']['discount'] = "unset";
            }
        }

        if ($this->compare->isDifferent($subscription, $expectedValues))
            throw new CacheInvalidationException("Non-updateable subscription details have changed: " . $this->compare->lastReason);
    }

    // WARNING
    // This is used by the CLI subscription creation command.
    // It does not try to collect initial fees or payments for non-subscription items on the order.
    // It also ignores trial periods on the subscription profile and only sets a trial if passed as a parameter.
    public function createSubscriptionFromOrder(
        $order,
        \StripeIntegration\Payments\Model\StripeCustomer $stripeCustomerModel,
        ?string $paymentMethodId = null,
        ?int $trialEnd = null
    )
    {
        if (!$this->config->isSubscriptionsEnabled())
        {
            throw new GenericException("Subscriptions are disabled");
        }

        $subscription = $this->getSubscriptionFromOrder($order);

        $stripeProductModel = $this->stripeProductFactory->create()->fromOrderItem($subscription['order_item']);
        $recurringPrice = $this->createSubscriptionPriceForSubscription($subscription['profile'], $stripeProductModel);
        $metadata = $this->collectMetadataForSubscription($subscription['profile']);

        $subscriptionItems[] = [
            "metadata" => $metadata,
            "price" => $recurringPrice->id,
            "quantity" => 1
        ];

        $params = [
            'description' => $this->orderHelper->getOrderDescription($order),
            'customer' => $stripeCustomerModel->getStripeId(),
            'items' => $subscriptionItems,
            'expand' => $this->getSubscriptionExpandParams(),
            'metadata' => $metadata,
            'payment_settings' => [
                'save_default_payment_method' => 'on_subscription'
            ]
        ];

        if (!empty($paymentMethodId))
        {
            $params['default_payment_method'] = $paymentMethodId;
            $stripeCustomerModel->attachPaymentMethod($paymentMethodId);
        }
        else
        {
            $params['payment_behavior'] = "allow_incomplete";
        }

        if (!empty($subscription['profile']['expiring_coupon']))
        {
            $coupon = $this->stripeCouponFactory->create()->fromSubscriptionProfile($subscription['profile']);
            if ($coupon->getId() && $coupon->getStripeObject()->duration == "forever")
            {
                $params['coupon'] = $coupon->getId();
            }
        }

        if (!empty($trialEnd))
        {
            $params["trial_end"] = $trialEnd;
        }

        $subscription = $this->config->getStripeClient()->subscriptions->create($params);
        $this->updateSubscriptionEntry($subscription, $order);
        return $subscription;
    }

    public function createSubscription($subscriptionCreationParams, $order, $profile)
    {
        $hasOneTimePayment = !empty($subscriptionCreationParams['add_invoice_items']);
        $startDateModel = $this->startDateFactory->create()->fromProfile($profile);
        $startDateParams = $startDateModel->getParams($hasOneTimePayment);

        if ($startDateModel->hasPhases())
        {
            $schedule = $this->subscriptionScheduleFactory->create([
                'subscriptionCreateParams' => $subscriptionCreationParams,
                'startDate' => $startDateModel,
            ]);

            $subscription = $schedule->create()->finalize()->getSubscription();

            $order->getPayment()->setAdditionalInformation('subscription_schedule_id', $schedule->getId());
        }
        else if (!empty($startDateParams))
        {
            $subscriptionCreationParams = array_merge_recursive($subscriptionCreationParams, $startDateParams);
            $subscription = $this->config->getStripeClient()->subscriptions->create($subscriptionCreationParams);
        }
        else
        {
            $subscription = $this->config->getStripeClient()->subscriptions->create($subscriptionCreationParams);
        }

        $this->updateSubscriptionEntry($subscription, $order);
        return $subscription;
    }

    public function updateSubscriptionFromOrder($order, $subscriptionId, $paymentIntentParams)
    {
        $subscription = $this->getSubscriptionFromOrder($order);

        if (empty($subscription))
            return null;

        $profile = $subscription['profile'];
        $params = $this->getSubscriptionParamsFromOrder($order, $paymentIntentParams);

        if (empty($params))
            return null;

        if (!empty($params['default_payment_method']))
        {
            $this->customer->attachPaymentMethod($params['default_payment_method']);
        }

        $startDateModel = $this->startDateFactory->create()->fromProfile($profile);
        if ($startDateModel->hasStartDate())
        {
            $this->checkoutFlow->isFutureSubscriptionSetup = true;
        }

        if (!$subscriptionId)
        {
            $checkoutSession = $this->paymentsHelper->getCheckoutSession();
            $subscriptionReactivateDetails = $checkoutSession->getSubscriptionReactivateDetails();
            if ($subscriptionReactivateDetails) {
                if (isset($subscriptionReactivateDetails['update_subscription_id'])
                    && $subscriptionReactivateDetails['update_subscription_id']) {
                    $subscriptionModel = $this->loadSubscriptionModelBySubscriptionId($subscriptionReactivateDetails['update_subscription_id']);
                    if ($subscriptionModel)
                    {
                        $subscriptionModel->setStatus('reactivated');
                        $subscriptionModel->save();
                    }
                }

                if (isset($subscriptionReactivateDetails['subscription_data']) && $subscriptionReactivateDetails['subscription_data'])
                {
                    if (!empty($params['default_payment_method']))
                    {
                        $subscriptionReactivateDetails['subscription_data']['default_payment_method'] = $params['default_payment_method'];
                    }
                    $subscriptionReactivateDetails['subscription_data']['metadata'] = $params['metadata'];
                    $params = $subscriptionReactivateDetails['subscription_data'];
                }
            }

            return $this->createSubscription($params, $order, $subscription['profile']);
        }

        $subscription = $this->config->getStripeClient()->subscriptions->retrieve($subscriptionId, [
            'expand' => $this->getSubscriptionExpandParams()
        ]);

        try
        {
            $this->invalidateSubscription($subscription, $params);
        }
        catch (CacheInvalidationException $e)
        {
            $this->config->getStripeClient()->subscriptions->cancel($subscription->id, []);
            return $this->createSubscription($params, $order, $profile);
        }

        $updateParams = $this->filterToUpdateableParams($params);

        if (empty($updateParams))
        {
            $this->updateSubscriptionEntry($subscription, $order);
            return $subscription;
        }

        if ($this->compare->isDifferent($subscription, $updateParams))
        {
            $subscription = $this->config->getStripeClient()->subscriptions->update($subscriptionId, $updateParams);
        }

        if (!empty($params['expand']))
        {
            $updateParams['expand'] = $params['expand'];
        }

        if (!empty($subscription->latest_invoice->payment_intent->id))
        {
            $params = [];
            $params["description"] = $this->orderHelper->getOrderDescription($order);
            $params["metadata"] = $this->config->getMetadata($order);

            $shipping = $this->addressHelper->getShippingAddressFromOrder($order);
            if ($shipping)
                $params['shipping'] = $shipping;

            if (!empty($updateParams['default_payment_method']))
                $params['payment_method'] = $updateParams['default_payment_method'];

            $updateParams = $this->paymentIntentHelper->getFilteredParamsForUpdate($params, $subscription->latest_invoice->payment_intent);
            $paymentIntent = $this->config->getStripeClient()->paymentIntents->update($subscription->latest_invoice->payment_intent->id, $updateParams);
            $subscription->latest_invoice->payment_intent = $paymentIntent;
        }

        $this->updateSubscriptionEntry($subscription, $order);

        return $subscription;
    }

    // Used by the CLI migration tool
    public function updateSubscriptionPriceFromOrder($subscription, $order, bool $prorate = false)
    {
        $upcomingInvoice = $this->config->getStripeClient()->invoices->upcoming(['subscription' => $subscription->id ]);
        if (!empty($upcomingInvoice->discount))
        {
            throw new GenericException("This subscription cannot be changed because it's upcoming invoice includes a discount coupon.");
        }

        $paymentIntentModel = $this->paymentIntentModelFactory->create();
        $paymentIntentParams = $paymentIntentModel->getParamsFrom($order);
        $params = $this->getSubscriptionParamsFromOrder($order, $paymentIntentParams);

        if (empty($params['items']) || empty($params['metadata']))
            throw new GenericException("Could not update subscription price.");

        $deletedItems = [];
        foreach ($subscription->items->data as $lineItem)
        {
            $deletedItems[] = [
                "id" => $lineItem['id'],
                "deleted" => true
            ];
        }

        $items = array_merge($deletedItems, $params['items']);
        $updateParams = [
            'items' => $items,
            'metadata' => $params['metadata']
        ];

        if (!$prorate)
        {
            $updateParams["proration_behavior"] = "none";
        }

        return $this->config->getStripeClient()->subscriptions->update($subscription->id, $updateParams);
    }

    public function isSuccessfulStatus($subscription)
    {
        if (!isset($subscription->status))
        {
            throw new GenericException("Invalid subscription passed as a method parameter");
        }

        return in_array($subscription->status, ["active", "trialing"]);
    }

    public function getSubscriptionItemsFromSubscriptionDetails($subscription)
    {
        if (empty($subscription))
            return null;

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

        $recurringPrice = $this->createSubscriptionPriceForSubscription($subscription['profile'], $stripeProductModel);

        $items = [];
        $metadata = $this->collectMetadataForSubscription($subscription['profile']);

        $items[] = [
            "metadata" => $metadata,
            "price" => $recurringPrice->id,
            "quantity" => 1
        ];

        return $items;
    }

    /**
     * Returns array [
     *   [
     *     \Magento\Catalog\Model\Product,
     *     \Magento\Sales\Model\Quote\Item,
     *     array $profile
     *   ],
     *   ...
     * ]
     */
    private function getSubscriptionsFromQuote($quote)
    {
        if (!$this->config->isSubscriptionsEnabled())
            return [];

        $items = $quote->getAllItems();
        $subscriptions = [];

        foreach ($items as $item)
        {
            $subscriptionProductModel = $this->subscriptionProductFactory->create()->fromQuoteItem($item);
            if (!$subscriptionProductModel->isSubscriptionProduct())
                continue;

            $product = $subscriptionProductModel->getProduct();

            try
            {
                $subscriptions[] = [
                    'product' => $product,
                    'quote_item' => $item,
                    'profile' => $this->getSubscriptionDetails($subscriptionProductModel, $quote, $item)
                ];
            }
            catch (\StripeIntegration\Payments\Exception\InvalidSubscriptionProduct $e)
            {
                continue;
            }
        }

        return $subscriptions;
    }

    public function getSubscriptionFromQuote($quote)
    {
        $subscriptions = $this->getSubscriptionsFromQuote($quote);

        if (empty($subscriptions))
        {
            return null;
        }

        if (count($subscriptions) > 1)
        {
            throw new LocalizedException(__("Only one subscription is allowed per order."));
        }

        return array_pop($subscriptions);
    }

    /**
     * Returns array [
     *   [
     *     \Magento\Catalog\Model\Product,
     *     \Magento\Sales\Model\Order\Item,
     *     array $profile
     *   ],
     *   ...
     * ]
     */
    public function getSubscriptionsFromOrder($order)
    {
        if (!$this->config->isSubscriptionsEnabled())
            return [];

        $items = $order->getAllItems();
        $subscriptions = [];

        foreach ($items as $item)
        {
            $subscriptionProductModel = $this->subscriptionProductFactory->create()->fromOrderItem($item);
            if (!$subscriptionProductModel->isSubscriptionProduct())
                continue;

            $product = $subscriptionProductModel->getProduct();

            try
            {
                $subscriptions[] = [
                    'product' => $product,
                    'order_item' => $item,
                    'profile' => $this->getSubscriptionDetails($subscriptionProductModel, $order, $item)
                ];
            }
            catch (\StripeIntegration\Payments\Exception\InvalidSubscriptionProduct $e)
            {
                continue;
            }
        }

        return $subscriptions;
    }

    public function getSubscriptionProductFromOrder($order)
    {
        if (!$this->config->isSubscriptionsEnabled())
            return [];

        $items = $order->getAllItems();

        foreach ($items as $item) {
            $subscriptionProductModel = $this->subscriptionProductFactory->create()->fromOrderItem($item);
            if (!$subscriptionProductModel->isSubscriptionProduct())
                continue;

            return [
                'product' => $subscriptionProductModel->getProduct(),
                'order_item' => $item
            ];
        }

        return null;
    }

    public function getSubscriptionFromOrder($order)
    {
        $subscriptions = $this->getSubscriptionsFromOrder($order);

        if (empty($subscriptions))
        {
            return null;
        }

        if (count($subscriptions) > 1)
        {
            throw new LocalizedException(__("Only one subscription is allowed per order."));
        }

        return array_pop($subscriptions);
    }

    public function getQuote()
    {
        $quote = $this->quoteHelper->getQuote();
        $createdAt = $quote->getCreatedAt();
        if (empty($createdAt)) // case of admin orders
        {
            $quoteId = $quote->getQuoteId();
            $quote = $this->quoteHelper->loadQuoteById($quoteId);
        }
        return $quote;
    }

    public function isOrder($order)
    {
        if (!empty($order->getOrderCurrencyCode()))
            return true;

        return false;
    }

    private function getProductOptionFor($item)
    {
        if (!$item->getParentItem())
            return null;

        $name = $item->getName();

        if ($productOptions = $item->getParentItem()->getProductOptions())
        {
            if (!empty($productOptions["bundle_options"]))
            {
                foreach ($productOptions["bundle_options"] as $bundleOption)
                {
                    if (!empty($bundleOption["value"]))
                    {
                        foreach ($bundleOption["value"] as $value)
                        {
                            if ($value["title"] == $name)
                            {
                                return $value;
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    public function getVisibleSubscriptionItem($item)
    {
        if ($item->getParentItem() && $item->getParentItem()->getProductType() == "configurable")
        {
            return $item->getParentItem();
        }
        else if ($item->getParentItem() && $item->getParentItem()->getProductType() == "bundle")
        {
            return $item->getParentItem();
        }
        else
            return $item;
    }

    // Initial fee amounts take into account the QTY ordered
    public function getInitialFeeDetails($product, $order, $item)
    {
        $details = [
            'initial_fee' => 0,
            'base_initial_fee' => 0,
            'tax' => 0,
            'base_tax' => 0
        ];

        if ($order->getPayment() && $order->getPayment()->getAdditionalInformation("remove_initial_fee"))
        {
            return $details;
        }

        $subscriptionOptionDetails = $this->getSubscriptionOptionDetails($product->getId());
        if (!$subscriptionOptionDetails)
        {
            return $details;
        }

        $initialFee = is_numeric($subscriptionOptionDetails->getSubInitialFee()) ? $subscriptionOptionDetails->getSubInitialFee() : 0;
        if (!$initialFee)
        {
            return $details;
        }

        $originalItem = $item;
        $originalQty = max(/* quote */ $item->getQty(), /* order */ $item->getQtyOrdered());

        $item = $this->getVisibleSubscriptionItem($item);
        $qty = max(/* quote */ $item->getQty(), /* order */ $item->getQtyOrdered());

        if ($item->getProductType() == "bundle")
        {
            $subSelectionQty = $originalQty;
            $bundleOption = $this->getProductOptionFor($originalItem);

            if ($item->getQtyOptions())
            {
                // Case hits when adding a product to the cart
                $details['base_initial_fee'] = 0;
                foreach ($item->getQtyOptions() as $qtyOption)
                {
                    if ($qtyOption->getProductId() == $originalItem->getProductId())
                    {
                        $subSelectionQty = $qtyOption->getValue();
                    }
                }
            }
            else if (isset($bundleOption['qty']) && is_numeric($bundleOption['qty']) && $bundleOption['qty'] > 0)
            {
                // Case hits in the admin area
                $subSelectionQty = $bundleOption['qty'];
            }

            $details['base_initial_fee'] = $initialFee * $subSelectionQty * $qty;
        }
        else
        {
            $details['base_initial_fee'] = $initialFee * $qty;
        }

        if (!is_numeric($details['base_initial_fee']))
            $details['base_initial_fee'] = 0;

        $taxPercent = $item->getTaxPercent();
        if (!$item->getTaxPercent() && $originalItem->getTaxPercent())
        {
            // Hits in the test suite
            $taxPercent = $originalItem->getTaxPercent();
        }

        if ($this->isOrder($order))
        {
            $rate = $order->getBaseToOrderRate();
        }
        else
        {
            $rate = $order->getBaseToQuoteRate();
        }

        if (is_numeric($rate) && $rate > 0)
        {
            $details['initial_fee'] = round(floatval($details['base_initial_fee'] * $rate), 2);
        }
        else
        {
            $details['initial_fee'] = $details['base_initial_fee'];
        }

        if ($this->config->priceIncludesTax())
        {
            $details['base_tax'] = $this->taxHelper->taxInclusiveTaxCalculator($details['base_initial_fee'], $taxPercent);
            $details['tax'] = $this->taxHelper->taxInclusiveTaxCalculator($details['initial_fee'], $taxPercent);
        }
        else
        {
            $details['base_tax'] = $this->taxHelper->taxExclusiveTaxCalculator($details['base_initial_fee'], $taxPercent);
            $details['tax'] = $this->taxHelper->taxExclusiveTaxCalculator($details['initial_fee'], $taxPercent);
        }

        $details['initial_fee'] = round(floatval($details['initial_fee']), 4);
        $details['base_initial_fee'] = round(floatval($details['base_initial_fee']), 4);
        $details['tax'] = round(floatval($details['tax']), 4);
        $details['base_tax'] = round(floatval($details['base_tax']), 4);

        return $details;
    }

    public function getSubscriptionDetails(\StripeIntegration\Payments\Model\SubscriptionProduct $subscriptionProductModel, $order, $item)
    {
        if (!$subscriptionProductModel->isSubscriptionProduct())
        {
            throw new InvalidSubscriptionProduct("This is not a subscription product.");
        }

        $originalItem = $item;

        $item = $this->getVisibleSubscriptionItem($item);

        $baseCurrency = $order->getBaseCurrencyCode();
        $deductedOrderAmount = 0;
        $baseDeductedOrderAmount = 0;

        if ($this->isOrder($order))
        {
            $orderIncrementId = $order->getIncrementId();
            $currency = $order->getOrderCurrencyCode();
            $qty = $item->getQtyOrdered();
            $subscriptionCart = $this->subscriptionCartFactory->create()->fromOrderItem($originalItem, $order);
        }
        else
        {
            $quote = $order;
            $orderIncrementId = $quote->getReservedOrderId();
            $currency = $quote->getQuoteCurrencyCode();
            $qty = $item->getQty();
            $subscriptionCart = $this->subscriptionCartFactory->create()->fromQuoteItem($originalItem, $quote);
        }

        $currencyPrecision = $this->convert->getCurrencyPrecision($currency);
        $baseCurrencyPrecision = $this->convert->getCurrencyPrecision($baseCurrency);
        $amount = $subscriptionCart->getSubscriptionPrice();
        $baseAmount = $subscriptionCart->getBaseSubscriptionPrice();
        $baseTax = $subscriptionCart->getBaseTaxAmount();
        $tax = $subscriptionCart->getTaxAmount();
        $shipping = $subscriptionCart->getShippingAmount();
        $baseShipping = $subscriptionCart->getBaseShippingAmount();
        $shippingTaxAmount = $subscriptionCart->getShippingTaxAmount();
        $shippingTaxPercent = $subscriptionCart->getShippingTaxPercent();
        $baseShippingTaxAmount = $subscriptionCart->getBaseShippingTaxAmount();
        $discount = $subscriptionCart->getDiscountAmount();
        $baseDiscount = $subscriptionCart->getBaseDiscountAmount();

        if ($subscriptionProductModel->hasZeroInitialOrderPrice() && $this->checkoutFlow->shouldNotBillTrialSubscriptionItems())
        {
            $deductedOrderAmount = $subscriptionCart->getGrandTotal() - $originalItem->getInitialFee();
            $baseDeductedOrderAmount = $subscriptionCart->getBaseGrandTotal() - $originalItem->getBaseInitialFee();

            if (!$this->config->priceIncludesTax())
            {
                $deductedOrderAmount -= $originalItem->getInitialFeeTax();
                $baseDeductedOrderAmount -= $originalItem->getBaseInitialFeeTax();
            }

            $subscriptionCart->setOriginalSubscriptionPrice($order);
        }

        $expiringCouponModel = $this->orderHelper->getExpiringCoupon($order);

        $params = [
            'name' => $item->getName(),
            'qty' => $qty,
            'interval' => $subscriptionProductModel->getInterval(),
            'interval_count' => $subscriptionProductModel->getIntervalCount(),
            'amount_magento' => $amount,
            'base_amount_magento' => $baseAmount,
            'amount_stripe' => $this->paymentsHelper->convertMagentoAmountToStripeAmount($amount, $currency),
            'initial_fee_magento' => $originalItem->getInitialFee(),
            'base_initial_fee_magento' => $originalItem->getBaseInitialFee(),
            'tax_amount_initial_fee' => $originalItem->getInitialFeeTax(),
            'base_tax_amount_initial_fee' => $originalItem->getBaseInitialFeeTax(),
            'initial_fee_stripe' => $this->paymentsHelper->convertMagentoAmountToStripeAmount($originalItem->getInitialFee(), $currency),
            'tax_amount_initial_fee_stripe' => $this->paymentsHelper->convertMagentoAmountToStripeAmount($originalItem->getInitialFeeTax(), $currency),
            'discount_amount_magento' => $discount,
            'base_discount_amount_magento' => $baseDiscount,
            'discount_amount_stripe' => $this->paymentsHelper->convertMagentoAmountToStripeAmount($discount, $currency),
            'shipping_magento' => round(floatval($shipping), $currencyPrecision),
            'base_shipping_magento' => round(floatval($baseShipping), $baseCurrencyPrecision),
            'shipping_stripe' => $this->paymentsHelper->convertMagentoAmountToStripeAmount($shipping, $currency),
            'currency' => strtolower($currency),
            'base_currency' => strtolower($baseCurrency),
            'tax_percent' => $item->getTaxPercent(),
            'tax_percent_shipping' => $shippingTaxPercent,
            'tax_amount_item' => $tax, // already takes $qty into account
            'base_tax_amount_item' => round(floatval($baseTax), $baseCurrencyPrecision), // already takes $qty into account
            'tax_amount_item_stripe' => $this->paymentsHelper->convertMagentoAmountToStripeAmount($tax, $currency), // already takes $qty into account
            'tax_amount_shipping' => round(floatval($shippingTaxAmount), $currencyPrecision),
            'base_tax_amount_shipping' => round(floatval($baseShippingTaxAmount), $baseCurrencyPrecision),
            'tax_amount_shipping_stripe' => $this->paymentsHelper->convertMagentoAmountToStripeAmount($shippingTaxAmount, $currency),
            'trial_end' => null,
            'trial_days' => $subscriptionProductModel->getTrialDays() ?? 0,
            'expiring_coupon' => ($expiringCouponModel ? $expiringCouponModel->getData() : null),
            'expiring_tax_amount_item' => 0,
            'expiring_base_tax_amount_item' => 0,
            'expiring_discount_amount_magento' => 0,
            'expiring_base_discount_amount_magento' => 0,
            'product_id' => $subscriptionProductModel->getProductId(),
            'deducted_order_amount' => $deductedOrderAmount,
            'base_deducted_order_amount' => $baseDeductedOrderAmount,
            'order_increment_id' => $orderIncrementId,
        ];

        $params = array_merge($params, $subscriptionProductModel->getSubscriptionDetails()->getData());

        if (!empty($params['expiring_coupon']))
        {
            // When the coupon expires, we want to increase the tax to the non-discounted amount, so we overwrite it here
            $taxAmountItem = round($params['amount_magento'] * $params['qty'] * ($params['tax_percent'] / 100), $currencyPrecision);
            $baseTaxAmountItem = round($params['base_amount_magento'] * $params['qty'] * ($params['tax_percent'] / 100), $baseCurrencyPrecision);
            $taxAmountItemStripe = $this->paymentsHelper->convertMagentoAmountToStripeAmount($taxAmountItem, $params['currency']);

            $diffTaxAmountItem = $taxAmountItem - $params['tax_amount_item'];
            $diffBaseTaxAmountItem = $baseTaxAmountItem - $params['base_tax_amount_item'];
            $diffTaxAmountItemStripe = $taxAmountItemStripe - $params['tax_amount_item_stripe'];

            // Increase the tax
            $params['tax_amount_item'] += $diffTaxAmountItem;
            $params['base_tax_amount_item'] += $diffBaseTaxAmountItem;
            $params['tax_amount_item_stripe'] += $diffTaxAmountItemStripe;

            // And also increase the discount to cover the tax of the non-discounted amount
            $params['discount_amount_magento'] += $diffTaxAmountItem;
            $params['base_discount_amount_magento'] += $diffBaseTaxAmountItem;
            $params['discount_amount_stripe'] += $diffTaxAmountItemStripe;

            // Set the expiring amount adjustments so that they offset the totals displayed at the front-end
            $params['expiring_tax_amount_item'] = $diffTaxAmountItem;
            $params['expiring_base_tax_amount_item'] = $diffBaseTaxAmountItem;
            $params['expiring_discount_amount_magento'] = $diffTaxAmountItem;
            $params['expiring_base_discount_amount_magento'] = $diffBaseTaxAmountItem;
        }

        return $params;
    }

    public function getTrialDays($product)
    {
        $subscriptionOptionDetails = $this->getSubscriptionOptionDetails($product->getId());
        $trialDays = $subscriptionOptionDetails->getSubTrial();
        if (!empty($trialDays) && is_numeric($trialDays) && $trialDays > 0)
            return $trialDays;

        return 0;
    }

    public function getSubscriptionTotalFromProfile($profile)
    {
        $subscriptionTotal =
            ($profile['qty'] * $profile['amount_magento']) +
            $profile['shipping_magento'] -
            $profile['discount_amount_magento'];

        if (!$this->config->shippingIncludesTax())
            $subscriptionTotal += $profile['tax_amount_shipping']; // Includes qty calculation

        if (!$this->config->priceIncludesTax())
            $subscriptionTotal += $profile['tax_amount_item']; // Includes qty calculation

        $total = round(floatval($subscriptionTotal), 2);

        return $total;
    }

    // We increase the subscription price by the amount of the discount, so that we can apply
    // a discount coupon on the amount and go back to the original amount AFTER the discount is applied
    public function getSubscriptionTotalWithDiscountAdjustmentFromProfile($profile)
    {
        $total = $this->getSubscriptionTotalFromProfile($profile);

        if (!empty($profile['expiring_coupon']))
            $total += $profile['discount_amount_magento'];

        return $total;
    }

    public function getStripeDiscountAdjustment($subscription)
    {
        $adjustment = 0;

        if (!empty($subscription['profile']))
        {
            $profile = $subscription['profile'];

            // This calculation only applies to MixedTrial carts
            if (!$profile['trial_days'])
                return 0;

            if (!empty($profile['expiring_coupon']))
                $adjustment += $profile['discount_amount_stripe'];
        }

        return $adjustment;
    }

    public function updateSubscriptionEntry($subscription, $order)
    {
        $entry = $this->subscriptionFactory->create();
        $entry->load($subscription->id, 'subscription_id');
        $entry->initFrom($subscription, $order);
        $entry->save();
        return $entry;
    }

    public function findSubscriptionItem($sub)
    {
        if (empty($sub->items->data))
            return null;

        /** @var \Stripe\SubscriptionItem $item */
        foreach ($sub->items->data as $item)
        {
            if (!empty($item->price->product->metadata->{"Type"}) && $item->price->product->metadata->{"Type"} == "Product" && $item->price->type == "recurring")
                return $item;
        }

        return null;
    }

    public function isStripeCheckoutSubscription($sub)
    {
        if (empty($sub->metadata->{"Order #"}))
            return false;

        $order = $this->orderHelper->loadOrderByIncrementId($sub->metadata->{"Order #"});

        if (!$order || !$order->getId())
            return false;

        return $this->paymentsHelper->isStripeCheckoutMethod($order->getPayment()->getMethod());
    }

    public function formatSubscriptionName(\Stripe\Subscription $sub)
    {
        $name = "";

        // Subscription Items
        if ($this->isStripeCheckoutSubscription($sub))
        {
            /** @var \Stripe\SubscriptionItem $item */
            $item =  $this->findSubscriptionItem($sub);

            if (!$item)
                return "Unknown subscription (err: 2)";

            if (!empty($item->price->product->name))
                $name = $item->price->product->name;
            else
                return "Unknown subscription (err: 3)";

            $currency = $item->price->currency;
            $amount = $item->price->unit_amount;
            $quantity = $item->quantity;
        }
        // Invoice Items
        else
        {
            if (!empty($sub->plan->name))
                $name = $sub->plan->name;

            if (empty($name) && isset($sub->plan->product) && is_numeric($sub->plan->product))
            {
                try
                {
                    $product = $this->productHelper->getProduct($sub->plan->product);
                    if ($product->getName())
                        $name = $product->getName();
                }
                catch (\Exception $e)
                {

                }

            }
            else
                return "Unknown subscription (err: 4)";

            $currency = $sub->plan->currency;
            $amount = $sub->plan->amount;
            $quantity = $sub->quantity;
        }

        $precision = $this->convert->getCurrencyPrecision($currency);
        $qty = '';
        $amount = $this->convert->stripeAmountToMagentoAmount($amount, $currency);

        if ($quantity > 1)
        {
            $qty = " x " . $quantity;
        }

        $this->priceCurrency->getCurrency()->setCurrencyCode(strtoupper($currency));
        $cost = $this->priceCurrency->format($amount, false, $precision);

        return "$name ($cost$qty)";
    }

    public function getSubscriptionsName($subscriptions)
    {
        $productNames = [];

        foreach ($subscriptions as $subscription)
        {
            $profile = $subscription['profile'];

            if ($profile['qty'] > 1)
                $productNames[] = $profile['qty'] . " x " . $profile['name'];
            else
                $productNames[] = $profile['name'];
        }

        $productName = implode(", ", $productNames);

        $productName = substr($productName, 0, 250);

        return $productName;
    }

    public function createSubscriptionPriceForSubscription($profile, $stripeProductModel)
    {
        if ($this->paymentsHelper->isMultiShipping())
            throw new GenericException("Price ID for multi-shipping subscriptions is not implemented", 1);

        $interval = $profile['interval'];
        $intervalCount = $profile['interval_count'];
        $currency = $profile['currency'];
        $magentoAmount = $this->getSubscriptionTotalWithDiscountAdjustmentFromProfile($profile);
        $stripeAmount = $this->paymentsHelper->convertMagentoAmountToStripeAmount($magentoAmount, $currency);

        $stripePriceModel = $this->stripePriceFactory->create()->fromData($stripeProductModel->getId(), $stripeAmount, $currency, $interval, $intervalCount);

        return $stripePriceModel->getStripeObject();
    }


    public function createPriceForOneTimePayment($stripeAmount, $currency)
    {
        $stripeProductModel = $this->stripeProductFactory->create()->fromData("one_time_payment", __("One time payment"));
        $stripePriceModel = $this->stripePriceFactory->create()->fromData($stripeProductModel->getId(), $stripeAmount, $currency);
        return $stripePriceModel->getStripeObject();
    }

    public function collectMetadataForSubscription($profile)
    {
        $subscriptionProductIds = [];

        if (empty($profile['product_id']))
            throw new GenericException("Could not find any subscription product IDs in cart subscriptions.");

        $subscriptionProductIds[] = $profile['product_id'];

        $metadata = [
            "Type" => "SubscriptionsTotal",
            "SubscriptionProductIDs" => implode(",", $subscriptionProductIds)
        ];

        if (!empty($profile['order_increment_id']))
        {
            $metadata["Order #"] = $profile['order_increment_id'];
        }

        return $metadata;
    }

    public function getTrialingSubscriptionsAmounts($quote = null)
    {
        if ($this->trialingSubscriptionsAmounts)
            return $this->trialingSubscriptionsAmounts;

        if (!$quote)
            $quote = $this->quoteHelper->getQuote();

        $trialingSubscriptionsAmounts = [
            "subscriptions_total" => 0,
            "base_subscriptions_total" => 0,
            "shipping_total" => 0,
            "base_shipping_total" => 0,
            "discount_total" => 0,
            "base_discount_total" => 0,
            "tax_total" => 0,
            "base_tax_total" => 0,
            "initial_fee" => 0,
            "base_initial_fee" => 0,
            "tax_amount_initial_fee" => 0,
            "base_tax_amount_initial_fee" => 0
        ];

        if (!$quote)
            return $trialingSubscriptionsAmounts;

        $this->trialingSubscriptionsAmounts = $trialingSubscriptionsAmounts;

        $items = $quote->getAllItems();
        foreach ($items as $item)
        {
            $subscriptionProductModel = $this->subscriptionProductFactory->create()->fromQuoteItem($item);

            if (!$subscriptionProductModel->isSubscriptionProduct())
                continue;

            if (!$subscriptionProductModel->hasTrialPeriod())
                continue;

            try
            {
                $profile = $this->getSubscriptionDetails($subscriptionProductModel, $quote, $item);
            }
            catch (\StripeIntegration\Payments\Exception\InvalidSubscriptionProduct $e)
            {
                continue;
            }

            $discountTotal = $profile["discount_amount_magento"] - $profile['expiring_discount_amount_magento'];
            $baseDiscountTotal = $profile["base_discount_amount_magento"] - $profile['expiring_base_discount_amount_magento'];

            $taxAmountItem = $profile["tax_amount_item"] - $profile['expiring_tax_amount_item'];
            $baseTaxAmountItem = $profile["base_tax_amount_item"] - $profile['expiring_base_tax_amount_item'];

            $taxAmountShipping = $profile["tax_amount_shipping"];
            $baseTaxAmountShipping = $profile["base_tax_amount_shipping"];

            $this->trialingSubscriptionsAmounts["subscriptions_total"] += $profile["amount_magento"] * $profile["qty"];
            $this->trialingSubscriptionsAmounts["base_subscriptions_total"] += $profile["base_amount_magento"] * $profile["qty"];
            $this->trialingSubscriptionsAmounts["shipping_total"] += $profile["shipping_magento"];
            $this->trialingSubscriptionsAmounts["base_shipping_total"] += $profile["base_shipping_magento"];
            $this->trialingSubscriptionsAmounts["discount_total"] += $discountTotal;
            $this->trialingSubscriptionsAmounts["base_discount_total"] += $baseDiscountTotal;
            $this->trialingSubscriptionsAmounts["tax_total"] += $taxAmountItem + $taxAmountShipping + $profile["tax_amount_initial_fee"];
            $this->trialingSubscriptionsAmounts["base_tax_total"] += $baseTaxAmountItem + $baseTaxAmountShipping + $profile["base_tax_amount_initial_fee"];
            $this->trialingSubscriptionsAmounts["base_initial_fee"] += $profile["base_initial_fee_magento"];
            $this->trialingSubscriptionsAmounts["initial_fee"] += $profile["initial_fee_magento"];
            $this->trialingSubscriptionsAmounts["tax_amount_initial_fee"] += $profile["tax_amount_initial_fee"];
            $this->trialingSubscriptionsAmounts["base_tax_amount_initial_fee"] += $profile["base_tax_amount_initial_fee"];

            $inclusiveTax = $baseInclusiveTax = 0;
            if ($this->config->shippingIncludesTax())
            {
                $inclusiveTax += $taxAmountShipping;
                $baseInclusiveTax += $baseTaxAmountShipping;
            }

            if ($this->config->priceIncludesTax())
            {
                // Adding the initial fee tax here because initial fee should be considered part of the price
                $inclusiveTax += $taxAmountItem + $profile["tax_amount_initial_fee"];
                $baseInclusiveTax += $baseTaxAmountItem + $profile["base_tax_amount_initial_fee"];
            }
            $this->trialingSubscriptionsAmounts["tax_inclusive"] = $inclusiveTax;
            $this->trialingSubscriptionsAmounts["base_tax_inclusive"] = $baseInclusiveTax;
        }

        foreach ($this->trialingSubscriptionsAmounts as $key => $amount)
        {
            $this->trialingSubscriptionsAmounts[$key] = round($amount, 2);
        }

        return $this->trialingSubscriptionsAmounts;
    }

    public function formatInterval($stripeAmount, $currency, $intervalCount, $intervalUnit)
    {
        $amount = $this->paymentsHelper->formatStripePrice($stripeAmount, $currency);

        if ($intervalCount > 1)
            return __("%1 every %2 %3", $amount, $intervalCount, $intervalUnit . "s");
        else
            return __("%1 every %2", $amount, $intervalUnit);
    }

    public function createQuoteFromOrder($originalOrder)
    {
        $recurringOrder = $this->recurringOrderFactory->create();
        $quote = $recurringOrder->createQuoteFrom($originalOrder);
        $recurringOrder->setQuoteCustomerFrom($originalOrder, $quote);
        $recurringOrder->setQuoteAddressesFrom($originalOrder, $quote);

        $invoiceDetails = [
            'products' => []
        ];

        foreach ($originalOrder->getAllItems() as $orderItem)
        {
            $subscriptionProductModel = $this->subscriptionProductFactory->create()->fromOrderItem($orderItem);

            if ($subscriptionProductModel->isSubscriptionProduct())
            {
                $invoiceDetails['products'][$orderItem->getProductId()] = [
                    'amount' => $orderItem->getPrice(),
                    'base_amount' => $orderItem->getBasePrice(),
                    'qty' => $orderItem->getQtyOrdered()
                ];
            }
        }

        if (empty($invoiceDetails['products']))
        {
            throw new GenericException("Order #" . $originalOrder->getIncrementId() . " does not include any subscriptions.");
        }

        $recurringOrder->setQuoteItemsFrom($originalOrder, $invoiceDetails, $quote);
        $recurringOrder->setQuoteShippingMethodFrom($originalOrder, $quote);
        $recurringOrder->setQuoteDiscountFrom($originalOrder, $quote, null);
        $recurringOrder->setQuotePaymentMethodFrom($originalOrder, $quote);

        // Collect Totals & Save Quote
        $quote->setTotalsCollectedFlag(false)->collectTotals();
        return $quote;
    }

    public function getSubscriptionProductIDs($subscription)
    {
        $productIDs = [];

        if (isset($subscription->metadata->{"Product ID"}))
        {
            $productIDs = explode(",", $subscription->metadata->{"Product ID"});
        }
        else if (isset($subscription->metadata->{"SubscriptionProductIDs"}))
        {
            $productIDs = explode(",", $subscription->metadata->{"SubscriptionProductIDs"});
        }

        return $productIDs;
    }

    public function getSubscriptionOrderID(\Stripe\Subscription $subscription)
    {
        if (isset($subscription->metadata->{"Order #"}))
        {
            return $subscription->metadata->{"Order #"};
        }

        return null;
    }

    public function getInvoiceAmount(\Stripe\Subscription $subscription)
    {
        $total = 0;
        $currency = null;

        if (empty($subscription->items->data))
            return __("Billed");

        foreach ($subscription->items->data as $item)
        {
            $amount = 0;
            $qty = $item->quantity;

            if (!empty($item->price->type) && $item->price->type != "recurring")
                continue;

            if (!empty($item->price->unit_amount))
                $amount = $qty * $item->price->unit_amount;

            if (!empty($item->price->currency))
                $currency = $item->price->currency;

            if (!empty($item->tax_rates[0]->percentage))
            {
                $rate = 1 + $item->tax_rates[0]->percentage / 100;
                $amount = $rate * $amount;
            }

            $total += $amount;
        }

        return $this->paymentsHelper->formatStripePrice($total, $currency);
    }

    public function formatDelivery(\Stripe\Subscription $subscription)
    {
        $interval = $subscription->plan->interval;
        $count = $subscription->plan->interval_count;

        if ($count > 1)
            return __("every %1 %2", $count, __($interval . "s"));
        else
            return __("every %1", __($interval));
    }

    protected function hasStartDate(\Stripe\Subscription $subscription)
    {
        // In cases where the billing cycle anchor is in the future
        if ($subscription->latest_invoice == null)
            return true;

        // In cases where a trial was set on the subscription with the aim of starting it in the future
        if (empty($subscription->metadata->{"Start Date"}))
            return false;

        $startDate = $subscription->metadata->{"Start Date"};
        $startDate = strtotime($startDate);

        if ($startDate > time())
            return true;

        return false;
    }

    public function formatLastBilled(\Stripe\Subscription $subscription)
    {
        $date = $subscription->current_period_start;
        $hasStartDate = $this->hasStartDate($subscription);

        if ($hasStartDate)
        {
            $date = $subscription->current_period_end;
            $day = date("j", $date);
            $sup = date("S", $date);
            $month = date("F", $date);

            return __("starting on %1<sup>%2</sup>&nbsp;%3", $day, $sup, $month);
        }
        else if ($subscription->status == "trialing")
        {
            $startDate = $subscription->current_period_end;
            $day = date("j", $startDate);
            $sup = date("S", $startDate);
            $month = date("F", $startDate);

            return __("trialing until %1<sup>%2</sup> %3", $day, $sup, $month);
        }
        else
        {
            $day = date("j", $date);
            $sup = date("S", $date);
            $month = date("F", $date);

            return __("last billed %1<sup>%2</sup>&nbsp;%3", $day, $sup, $month);
        }
    }

    public function getUpcomingInvoice($prorationTimestamp = null)
    {
        $checkoutSession = $this->paymentsHelper->getCheckoutSession();
        $subscriptionUpdateDetails = $checkoutSession->getSubscriptionUpdateDetails();
        if (!$subscriptionUpdateDetails)
            return null;

        if (!$prorationTimestamp)
        {
            if (!empty($subscriptionUpdateDetails['_data']['proration_timestamp']))
            {
                $prorationTimestamp = $subscriptionUpdateDetails['_data']['proration_timestamp'];
            }
            else
            {
                $prorationTimestamp = $subscriptionUpdateDetails['_data']['proration_timestamp'] = time();
                $checkoutSession->setSubscriptionUpdateDetails($subscriptionUpdateDetails);
            }
        }

        $items = [];
        if ($subscriptionUpdateDetails && !empty($subscriptionUpdateDetails['_data']['subscription_id']))
        {
            $oldSubscriptionId = $subscriptionUpdateDetails['_data']['subscription_id'];
            $stripeSubscriptionModel = $this->stripeSubscriptionFactory->create()->fromSubscriptionId($oldSubscriptionId);
            $invoicePreview = $stripeSubscriptionModel->getUpcomingInvoiceAfterUpdate($prorationTimestamp);
            $oldPrice = $invoicePreview->oldPriceId;
            $newPrice = $invoicePreview->newPriceId;
            $quote = $this->quoteHelper->getQuote();
            $remainingAmount = $unusedAmount = $subscriptionAmount = 0;
            $remainingLineItem = null;
            $labels = [
                'remaining' => null,
                'unused' => null,
                'subscription' => null
            ];

            $comments = [];

            foreach ($invoicePreview->lines->data as $invoiceItem)
            {
                $invoiceItemMagentoAmount = $this->paymentsHelper->formatStripePrice($invoiceItem->amount, $invoiceItem->currency);
                if ($invoiceItemMagentoAmount == "-")
                {
                    // Add negative amount at the end
                    $comments[] = $invoiceItemMagentoAmount . " " . lcfirst($invoiceItem->description);
                }
                else
                {
                    // Add positive amounts at the beginning
                    array_unshift($comments, $invoiceItemMagentoAmount . " " . lcfirst($invoiceItem->description));
                }

                if ($invoiceItem->type == "subscription")
                {
                    $subscriptionAmount += $invoiceItem->amount;
                    $labels['subscription'] = $this->formatInterval(
                        $subscriptionAmount,
                        $invoiceItem->currency,
                        $invoiceItem->price->recurring->interval_count,
                        $invoiceItem->price->recurring->interval
                    );
                }
                else if ($invoiceItem->amount < 0)
                {
                    $unusedAmount += $invoiceItem->amount;
                    $labels['unused'] = $this->paymentsHelper->formatStripePrice($unusedAmount, $invoiceItem->currency);
                }
                else if ($invoiceItem->amount > 0)
                {
                    $remainingAmount += $invoiceItem->amount;
                    $remainingLineItem = $invoiceItem;
                    $labels['remaining'] = $this->paymentsHelper->formatStripePrice($remainingAmount, $invoiceItem->currency);
                    if (empty($labels['subscription']))
                    {
                        $labels['subscription'] = $this->formatInterval(
                            $remainingAmount,
                            $invoiceItem->currency,
                            $invoiceItem->price->recurring->interval_count,
                            $invoiceItem->price->recurring->interval
                        );
                    }
                }
            }

            // Update the order comments
            if (empty($comments))
            {
                $subscriptionUpdateDetails['_data']['comments'] = null;
            }
            else
            {
                $subscriptionUpdateDetails['_data']['comments'] = implode(", ", $comments);
            }

            $checkoutSession->setSubscriptionUpdateDetails($subscriptionUpdateDetails);

            if ($unusedAmount < 0)
            {
                $items["unused_time"] = [
                    "amount" => $this->paymentsHelper->convertStripeAmountToQuoteAmount($unusedAmount, $invoicePreview->currency, $quote),
                    "currency" => $invoicePreview->currency,
                    "label" => $labels['unused']
                ];
            }

            if ($remainingAmount > 0)
            {
                $items["proration_fee"] = [
                    "amount" => $this->paymentsHelper->convertStripeAmountToQuoteAmount($remainingAmount, $invoicePreview->currency, $quote),
                    "currency" => $invoicePreview->currency,
                    "label" => $labels['remaining']
                ];
            }

            $items["new_price"] = [
                "amount" => $this->paymentsHelper->convertStripeAmountToQuoteAmount($quote->getGrandTotal(), $invoicePreview->currency, $quote),
                "currency" => $invoicePreview->currency,
                "label" => $this->paymentsHelper->addCurrencySymbol($quote->getGrandTotal(), $invoicePreview->currency)
            ];

            if ($invoicePreview->ending_balance < 0)
            {
                $amount = $this->paymentsHelper->convertStripeAmountToQuoteAmount(-$invoicePreview->ending_balance, $invoicePreview->currency, $quote);
                $amount = $this->paymentsHelper->addCurrencySymbol($amount, $invoicePreview->currency);
                $items['credit'] = __("Your account's credit of %1 will be used to offset future subscription payments.", $amount);
            }

            $stripeBalance = min($invoicePreview->amount_remaining, $invoicePreview->total);
            if (!empty($stripeBalance))
            {
                $magentoBalance = $this->paymentsHelper->convertStripeAmountToQuoteAmount($stripeBalance, $invoicePreview->currency, $quote);
                $magentoBaseBalance = $this->paymentsHelper->convertStripeAmountToBaseQuoteAmount($stripeBalance, $invoicePreview->currency, $quote);

                // These will be added to the order grand total
                $items["proration_adjustment"] = max(0, $magentoBalance) - $quote->getGrandTotal();
                $items["base_proration_adjustment"] = max(0, $magentoBaseBalance) - $quote->getBaseGrandTotal();
            }

            return $items;
        }

        return null;
    }

    public function isSubscriptionReactivate()
    {
        return $this->checkoutSessionHelper->isSubscriptionReactivate();
    }

    public function getSubscriptionUpdateDetails()
    {
        return $this->checkoutSessionHelper->getSubscriptionUpdateDetails();
    }

    public function updateSubscription(\Magento\Payment\Model\InfoInterface $payment)
    {
        try
        {
            $subscriptionUpdateDetails = $this->getSubscriptionUpdateDetails();
            $oldSubscriptionId = $subscriptionUpdateDetails['_data']['subscription_id'];
            $stripeSubscriptionModel = $this->stripeSubscriptionFactory->create()->fromSubscriptionId($oldSubscriptionId);
            $stripeSubscriptionModel->performUpdate($payment);
        }
        catch (LocalizedException $e)
        {
            $this->paymentsHelper->logError($e->getMessage(), $e->getTraceAsString());
            throw $e;
        }
        catch (\Exception $e)
        {
            $this->paymentsHelper->logError($e->getMessage(), $e->getTraceAsString());
            throw new LocalizedException(__("Sorry, the order could not be placed. Please contact us for assistance."));
        }
    }

    public function cancelSubscriptionUpdate($silent = false)
    {
        if (!$this->config->isSubscriptionsEnabled())
            return;

        $checkoutSession = $this->paymentsHelper->getCheckoutSession();
        $subscriptionUpdateDetails = $checkoutSession->getSubscriptionUpdateDetails();

        if (!$subscriptionUpdateDetails)
            return;

        $productNames = [];
        $quote = $this->quoteHelper->getQuote();
        $quoteItems = $quote->getAllVisibleItems();
        foreach ($quoteItems as $quoteItem)
        {
            $productNames[] = $quoteItem->getName();
            $quoteItem->delete();
        }
        $this->quoteHelper->saveQuote($quote);

        if (!$silent)
        {
            if (!empty($productNames))
            {
                $this->paymentsHelper->addWarning(__("The subscription update (%1) has been canceled.", implode(", ", $productNames)));
            }
            else
            {
                $this->paymentsHelper->addWarning(__("The subscription update has been canceled."));
            }
        }

        $checkoutSession->unsSubscriptionUpdateDetails();
    }

    public function loadSubscriptionModelBySubscriptionId($subscriptionId)
    {
        return $this->subscriptionCollectionFactory->create()->getBySubscriptionId($subscriptionId);
    }

    // Returns a minimal profile with just price data
    public function getCombinedProfileFromSubscriptions($subscriptions)
    {
        $combinedProfile = [
            "name" => $this->getSubscriptionsName($subscriptions),
            "magento_amount" => 0,
            "stripe_amount" => null,
            "interval" => null,
            "interval_count" => null,
            "currency" => null,
            "product_ids" => []
        ];

        foreach ($subscriptions as $subscription)
        {
            $profile = $subscription["profile"];

            if (empty($combinedProfile["currency"]))
            {
                $combinedProfile["currency"] = $profile["currency"];
            }
            else if ($combinedProfile["currency"] != $profile["currency"])
            {
                throw new GenericException("It is not possible to buy multiple subscriptions in different currencies.");
            }

            if (empty($combinedProfile["interval"]))
            {
                $combinedProfile["interval"] = $profile["interval"];
            }
            else if ($combinedProfile["interval"] != $profile["interval"])
            {
                throw new LocalizedException(__("Subscriptions that do not renew together must be bought separately."));
            }

            if (empty($combinedProfile["interval_count"]))
            {
                $combinedProfile["interval_count"] = $profile["interval_count"];
            }
            else if ($combinedProfile["interval_count"] != $profile["interval_count"])
            {
                throw new LocalizedException(__("Subscriptions that do not renew together must be bought separately."));
            }

            $combinedProfile["magento_amount"] += $this->getSubscriptionTotalWithDiscountAdjustmentFromProfile($profile);
            $combinedProfile["product_ids"][] = $profile["product_id"];
        }

        if (!$combinedProfile["currency"])
            throw new GenericException("No subscriptions specified.");

        $combinedProfile["stripe_amount"] = $this->paymentsHelper->convertMagentoAmountToStripeAmount($combinedProfile["magento_amount"], $combinedProfile["currency"]);

        return $combinedProfile;
    }

    public function isZeroAmountOrder($order)
    {
        $orderItems = $order->getAllItems();
        $trialSubscriptions = [];
        foreach ($orderItems as $orderItem)
        {
            try
            {
                $subscriptionProductModel = $this->subscriptionProductFactory->create()->fromOrderItem($orderItem);

                if ($subscriptionProductModel->isSubscriptionProduct() && $subscriptionProductModel->hasTrialPeriod())
                {
                    $trialSubscriptions[] = [
                        'product' => $subscriptionProductModel->getProduct(),
                        'order_item' => $orderItem,
                        'profile' => $this->getSubscriptionDetails($subscriptionProductModel, $order, $orderItem),
                    ];
                }
            }
            catch (\StripeIntegration\Payments\Exception\InvalidSubscriptionProduct $e)
            {
                // Some bundle products cause crashes
                continue;
            }
        }

        $charge = $order->getGrandTotal();

        if (!empty($trialSubscriptions))
        {
            $combinedProfile = $this->getCombinedProfileFromSubscriptions($trialSubscriptions);
            $charge = $order->getGrandTotal() - $combinedProfile['magento_amount'];
        }

        return ($charge < 0.005);
    }

    public function isZeroAmountCart()
    {
        $quote = $this->getQuote();

        if (empty($quote))
            return true;

        $quoteItems = $quote->getAllItems();

        $trialSubscriptions = [];
        foreach ($quoteItems as $quoteItem)
        {
            try
            {
                $subscriptionProductModel = $this->subscriptionProductFactory->create()->fromQuoteItem($quoteItem);

                if ($subscriptionProductModel->isSubscriptionProduct() && $subscriptionProductModel->hasTrialPeriod())
                {
                    $trialSubscriptions[] = [
                        'product' => $subscriptionProductModel->getProduct(),
                        'quote_item' => $quoteItem,
                        'profile' => $this->getSubscriptionDetails($subscriptionProductModel, $quote, $quoteItem),
                    ];
                }
            }
            catch (\StripeIntegration\Payments\Exception\InvalidSubscriptionProduct $e)
            {
                continue;
            }
        }

        $charge = $quote->getGrandTotal();

        if (!empty($trialSubscriptions))
        {
            $combinedProfile = $this->getCombinedProfileFromSubscriptions($trialSubscriptions);
            $charge -= $combinedProfile['magento_amount'];
        }

        return ($charge < 0.005);
    }

    /**
     * Get subscription option details
     */
    public function getSubscriptionOptionDetails(string $productId): ?\StripeIntegration\Payments\Model\SubscriptionOptions
    {
        $cacheKey = 'stripe_subscription_details_' . $productId;

        if (isset($this->localCache[$cacheKey])) {
            return $this->localCache[$cacheKey];
        }

        $subscriptionDetails = $this->subscriptionOptionsFactory->create()->load($productId);

        if (empty($subscriptionDetails->getProductId()))
        {
            $this->localCache[$cacheKey] = null;
        }
        else
        {
            $this->localCache[$cacheKey] = $subscriptionDetails;
        }

        return $this->localCache[$cacheKey];
    }

    public function getReactivatedSubscriptionItems($status)
    {
        return $this->subscriptionCollectionFactory->create()->getBySubscriptionStatus('canceled');
    }

    public function generateSubscriptionName($subscription)
    {
        $items = [];

        if (!empty($subscription->plan->product->name))
            return $subscription->plan->product->name;

        if (empty($subscription->items->data))
            return __("Subscription");

        foreach ($subscription->items->data as $item)
        {
            if ($item->quantity > 1)
                $qty = $item->quantity . " x ";
            else
                $qty = "";

            if (!empty($item->price->product->name))
                $items[] = $qty . $item->price->product->name;
        }

        return implode(", ", $items);
    }

    public function hasSubscriptions($quote = null)
    {
        if (empty($quote))
            $quote = $this->getQuote();

        if (empty($quote) || !$quote->getId())
            return false;

        return $this->quoteHelper->hasSubscriptions($quote);
    }

    public function hasTrialSubscriptions($quote = null)
    {
        if (!$quote)
            $quote = $this->getQuote();

        if (!$quote || !$quote->getId())
            return false;

        $cacheKey = 'quote_has_trial_subscriptions_' . $quote->getId();
        if (isset($this->localCache[$cacheKey])) {
            return $this->localCache[$cacheKey];
        }

        $items = $quote->getAllItems();

        return $this->localCache[$cacheKey] = $this->quoteHelper->hasTrialSubscriptionsIn($items);
    }

    public function hasOnlyTrialSubscriptionsIn($items)
    {
        if (!$this->config->isSubscriptionsEnabled())
            return false;

        $foundAtLeastOneTrialSubscriptionProduct = false;

        foreach ($items as $item)
        {
            if (!in_array($item->getProductType(), ["simple", "virtual", "downloadable", "giftcard"]))
                continue;

            try
            {
                $product = $this->productHelper->getProduct($item->getProductId());
            }
            catch (\Exception $e)
            {
                continue;
            }

            $subscriptionOptionDetails = $this->getSubscriptionOptionDetails($product->getId());

            if (!$subscriptionOptionDetails)
                continue;

            $trial = $subscriptionOptionDetails->getSubTrial();
            if (is_numeric($trial) && $trial > 0)
            {
                $foundAtLeastOneTrialSubscriptionProduct = true;
            }
            else
            {
                return false;
            }
        }

        return $foundAtLeastOneTrialSubscriptionProduct;
    }
}
