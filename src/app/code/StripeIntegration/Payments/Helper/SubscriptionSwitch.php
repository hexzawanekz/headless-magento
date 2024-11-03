<?php

namespace StripeIntegration\Payments\Helper;

use StripeIntegration\Payments\Exception\GenericException;

class SubscriptionSwitch
{
    private $subscriptions = [];
    private $transaction = null;
    private $stripeSubscriptionFactory;
    private $config;
    private $fromProduct;
    private $toProduct;
    private $transactionFactory;
    private $customer;
    private $recurringOrder;
    private $subscriptionsHelper;
    private $paymentsHelper;
    private $quoteHelper;
    private $subscriptionProductFactory;

    public function __construct(
        \StripeIntegration\Payments\Helper\Generic $paymentsHelper,
        \StripeIntegration\Payments\Helper\Quote $quoteHelper,
        \StripeIntegration\Payments\Helper\Subscriptions $subscriptionsHelper,
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Model\SubscriptionProductFactory $subscriptionProductFactory,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \StripeIntegration\Payments\Helper\RecurringOrder $recurringOrder,
        \StripeIntegration\Payments\Model\Stripe\SubscriptionFactory $stripeSubscriptionFactory
    ) {
        $this->paymentsHelper = $paymentsHelper;
        $this->quoteHelper = $quoteHelper;
        $this->subscriptionsHelper = $subscriptionsHelper;
        $this->config = $config;
        $this->customer = $paymentsHelper->getCustomerModel();
        $this->transactionFactory = $transactionFactory;
        $this->recurringOrder = $recurringOrder;
        $this->stripeSubscriptionFactory = $stripeSubscriptionFactory;
        $this->subscriptionProductFactory = $subscriptionProductFactory;
    }

    // This is called once, it loads all subscriptions from all configured Stripe accounts
    protected function initForOrder($order)
    {
        $store = $order->getStore();
        $storeId = $store->getId();

        if (!empty($this->subscriptions[$storeId]))
            return;

        $mode = $this->config->getConfigData("mode", "basic", $storeId);
        $currency = $store->getDefaultCurrency()->getCurrencyCode();

        if (!$this->config->reInitStripe($storeId, $currency, $mode))
            throw new GenericException("Order #" . $order->getIncrementId() . " could not be migrated because Stripe could not be initialized for store " . $store->getName() . " ($mode mode)");

        $params = [
            'limit' => 100
        ];

        $customerId = $order->getPayment()->getAdditionalInformation("customer_stripe_id");
        if (!empty($customerId))
            $params["customer"] = $customerId;

        $subscriptions = $this->config->getStripeClient()->subscriptions->all($params);

        foreach ($subscriptions->autoPagingIterator() as $key => $subscription)
        {
            if (!isset($subscription->metadata->{"Order #"}))
                continue;

            $stripeSubscriptionModel = $this->stripeSubscriptionFactory->create()->fromSubscription($subscription);

            $productIDs = $stripeSubscriptionModel->getProductIDs();

            foreach ($productIDs as $productID)
            {
                $this->subscriptions[$storeId][$subscription->metadata->{"Order #"}][$productID] = $subscription;
            }
        }
    }

    public function run($order, $fromProduct, $toProduct)
    {
        $this->initForOrder($order);

        if (!$order->getId())
            throw new GenericException("Invalid subscription order specified");

        if (!$fromProduct->getId() || !$toProduct->getId())
            throw new GenericException("Invalid subscription product specified");

        if (!$this->subscriptionProductFactory->create()->fromProductId($fromProduct->getId())->isSubscriptionProduct())
            throw new GenericException($this->fromProduct->getName() . " is not a subscription product");

        if (!$this->subscriptionProductFactory->create()->fromProductId($toProduct->getId())->isSubscriptionProduct())
            throw new GenericException($this->toProduct->getName() . " is not a subscription product");

        if (!$this->isSubscriptionActive($order->getStore()->getId(), $order->getIncrementId(), $fromProduct->getId()))
            return false;

        try
        {
            $this->transaction = $this->transactionFactory->create();
            $newOrder = $this->beginMigration($order, $fromProduct, $toProduct);
            $this->transaction->save();

            return true;
        }
        catch (\Exception $e)
        {
            $this->paymentsHelper->logError($e->getMessage(), $e->getTraceAsString());
            throw $e;
        }
    }

    protected function beginMigration($originalOrder, $fromProduct, $toProduct)
    {
        /** @var \Stripe\Subscription */
        $subscription = $this->subscriptions[$originalOrder->getStore()->getId()][$originalOrder->getIncrementId()][$fromProduct->getId()];

        $paymentMethodId = null;
        if (!empty($subscription->default_payment_method))
        {
            $paymentMethodId = $subscription->default_payment_method;
        }
        else if (!empty($subscription->latest_invoice))
        {
            $latestInvoice = $this->config->getStripeClient()->invoices->retrieve($subscription->latest_invoice, ['expand' => ['payment_intent']]);
            if (!empty($latestInvoice->payment_intent->payment_method))
            {
                $paymentMethodId = $latestInvoice->payment_intent->payment_method;
            }
            else
            {
                throw new GenericException("Cannot migrate subscription {$subscription->id} because it does not have a payment method.");
            }
        }

        $this->customer->fromStripeCustomerId($subscription->customer);

        $quote = $this->recurringOrder->createQuoteFrom($originalOrder);
        $quote->setIsRecurringOrder(false)->setRemoveInitialFee(true);
        $this->recurringOrder->setQuoteCustomerFrom($originalOrder, $quote);
        $this->recurringOrder->setQuoteAddressesFrom($originalOrder, $quote);
        $quote->addProduct($toProduct, $subscription->quantity);
        $this->recurringOrder->setQuoteShippingMethodFrom($originalOrder, $quote);
        $this->recurringOrder->setQuoteDiscountFrom($originalOrder, $quote, $subscription->discount);

        $data = [
            'additional_data' => [
                'payment_method' => $paymentMethodId,
                'is_migrated_subscription' => true
            ]
        ];
        $this->recurringOrder->setQuotePaymentMethodFrom($originalOrder, $quote, $data);
        $quote->getPayment()
            ->setAdditionalInformation("is_recurring_subscription", false)
            ->setAdditionalInformation("remove_initial_fee", true);

        // Collect Totals & Save Quote
        $quote->collectTotals();
        $this->quoteHelper->saveQuote($quote);

        // Create Order From Quote
        $order = $this->recurringOrder->quoteManagement->submit($quote);
        $this->transaction->addObject($order);

        // Cancel the newly created order
        $order->cancel();
        $order->setState('closed')->setStatus('closed');
        $comment = __("This order has been automatically closed because no payment has been collected for it. It can only be used as a billing details reference for the subscription items in the order. The subscription is still active and a new order will be created when it renews.");
        $order->addStatusToHistory($status = false, $comment, $isCustomerNotified = false);

        // Depreciate the old order
        $comment = __("The billing details for a subscription on this order have changed. Please see order #%1 for information on the new billing details.", $order->getIncrementId());
        $originalOrder->addStatusToHistory($status = false, $comment, $isCustomerNotified = false);
        $this->transaction->addObject($originalOrder);

        // Update the subscription price
        $subscription = $this->subscriptionsHelper->updateSubscriptionPriceFromOrder($subscription, $order);
        $order->getPayment()->setAdditionalInformation("subscription_id", $subscription->id);

        return $order;
    }

    protected function setTransactionDetailsFor($order, $transactionId)
    {
        $order->getPayment()
            ->setLastTransId($transactionId)
            ->setIsTransactionClosed(0)
            ->setIsTransactionPending(true);

        $this->transaction->addObject($order);
    }

    protected function isSubscriptionActive($storeId, $orderIncrementId, $productId)
    {
        if (!isset($this->subscriptions[$storeId][$orderIncrementId][$productId]))
            return false;

        $count = count($this->subscriptions[$storeId][$orderIncrementId]);
        if ($count > 1)
            throw new GenericException("The order includes multiple subscriptions.");

        $subscription = $this->subscriptions[$storeId][$orderIncrementId][$productId];

        if ($subscription->status == "active" || $subscription->status == "trialing")
            return true;

        return false;
    }
}
