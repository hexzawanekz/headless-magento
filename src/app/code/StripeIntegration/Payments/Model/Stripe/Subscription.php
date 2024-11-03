<?php

namespace StripeIntegration\Payments\Model\Stripe;

use Magento\Framework\Exception\LocalizedException;
use StripeIntegration\Payments\Helper\Data as DataHelper;
use StripeIntegration\Payments\Exception\GenericException;

class Subscription
{
    use StripeObjectTrait;

    private $objectSpace = 'subscriptions';
    private $canUpgradeDowngrade;
    private $canChangeShipping;
    private $useProrations;
    private $orderItems = [];
    private $subscriptionProductModels = [];
    private $order;
    private $paymentIntentModel;
    private $subscriptionProductFactory;
    private $dataObjectFactory;
    private $helper;
    private $config;
    private $subscriptionsHelper;
    private $dataHelper;
    private $quoteHelper;
    private $orderHelper;

    public function __construct(
        \StripeIntegration\Payments\Model\Stripe\Service\StripeObjectServicePool $stripeObjectServicePool,
        \Magento\Framework\DataObject\Factory $dataObjectFactory,
        \StripeIntegration\Payments\Model\PaymentIntent $paymentIntentModel,
        \StripeIntegration\Payments\Model\SubscriptionProductFactory $subscriptionProductFactory,
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Helper\Subscriptions $subscriptionsHelper,
        \StripeIntegration\Payments\Helper\Data $dataHelper,
        \StripeIntegration\Payments\Helper\Quote $quoteHelper,
        \StripeIntegration\Payments\Helper\Order $orderHelper
    )
    {
        $stripeObjectService = $stripeObjectServicePool->getStripeObjectService($this->objectSpace);
        $this->setData($stripeObjectService);

        $this->dataObjectFactory = $dataObjectFactory;
        $this->paymentIntentModel = $paymentIntentModel;
        $this->subscriptionProductFactory = $subscriptionProductFactory;
        $this->helper = $helper;
        $this->config = $config;
        $this->subscriptionsHelper = $subscriptionsHelper;
        $this->dataHelper = $dataHelper;
        $this->quoteHelper = $quoteHelper;
        $this->orderHelper = $orderHelper;
    }

    public function fromSubscriptionId($subscriptionId)
    {
        $this->getObject($subscriptionId);

        if (!$this->getStripeObject())
            throw new \Magento\Framework\Exception\LocalizedException(__("The subscription \"%1\" could not be found in Stripe: %2", $subscriptionId, $this->getLastError()));

        $this->fromSubscription($this->getStripeObject());

        return $this;
    }

    public function fromSubscription(\Stripe\Subscription $subscription)
    {
        $this->setObject($subscription);

        $productIDs = $this->getProductIDs();
        $order = $this->getOrder();

        if (empty($productIDs) || empty($order))
            return $this;

        $orderItems = $order->getAllItems();
        foreach ($orderItems as $orderItem)
        {
            if (in_array($orderItem->getProductId(), $productIDs))
            {
                $product = $this->subscriptionProductFactory->create()->fromOrderItem($orderItem);
                if ($product->isSubscriptionProduct() && in_array($product->getProductId(), $productIDs))
                {
                    $this->orderItems[$orderItem->getId()] = $orderItem;
                    $this->subscriptionProductModels[$orderItem->getId()] = $product;
                }
            }
        }

        return $this;
    }

    public function getOrder()
    {
        if (isset($this->order))
            return $this->order;

        $orderIncrementId = $this->getOrderID();
        if (empty($orderIncrementId))
            return null;

        $order = $this->orderHelper->loadOrderByIncrementId($orderIncrementId);
        if (!$order || !$order->getId())
            return null;

        return $this->order = $order;
    }

    public function getOrderItems()
    {
        return $this->orderItems;
    }

    public function canUpgradeDowngrade()
    {
        if (isset($this->canUpgradeDowngrade))
            return $this->canUpgradeDowngrade;

        if (!$this->config->isSubscriptionsEnabled())
            return $this->canUpgradeDowngrade = false;

        if ($this->getStripeObject()->status != "active")
            return $this->canUpgradeDowngrade = false;

        if ($this->isCompositeSubscription())
            return $this->canUpgradeDowngrade = false;

        foreach ($this->subscriptionProductModels as $subscriptionProduct)
        {
            /** @var \StripeIntegration\Payments\Model\SubscriptionProduct $subscriptionProduct */
            if ($subscriptionProduct->canChangeSubscription())
            {
                return $this->canUpgradeDowngrade = true;
            }
        }

        return $this->canUpgradeDowngrade = false;
    }

    public function getSubscriptionProductModel()
    {
        if (count($this->subscriptionProductModels) == 1)
            return reset($this->subscriptionProductModels);

        return null;
    }

    public function getOrderItem()
    {
        if (count($this->orderItems) == 1)
        {
            $orderItem = reset($this->orderItems);

            if ($orderItem->getParentItemId()) // Configurable subscriptions
                $orderItem = $this->getOrder()->getItemById($orderItem->getParentItemId());

            return $orderItem;
        }

        return null;
    }

    public function editUrl()
    {
        return $this->helper->getUrl('stripe/customer/subscriptions', ['edit' => $this->getStripeObject()->id]);
    }

    public function canChangeShipping()
    {
        if (isset($this->canChangeShipping))
            return $this->canChangeShipping;

        if (!$this->config->isSubscriptionsEnabled())
            return $this->canChangeShipping = false;

        if ($this->getStripeObject()->status != "active")
            return $this->canChangeShipping = false;

        foreach ($this->subscriptionProductModels as $subscriptionProduct)
        {
            if ($subscriptionProduct->canChangeShipping())
            {
                return $this->canChangeShipping = true;
            }
        }

        return $this->canChangeShipping = false;
    }

    public function getPriceChange(float $newStripeAmount)
    {
        $oldStripeAmount = $this->getStripeAmount();
        return ($newStripeAmount - $oldStripeAmount);
    }

    public function useProrations(float $newStripeAmount, array $newProductIds)
    {
        if (isset($this->useProrations))
        {
            return $this->useProrations;
        }

        if (!$this->config->isSubscriptionsEnabled())
        {
            return $this->useProrations = false;
        }

        $priceChange = $this->getPriceChange($newStripeAmount);

        if ($priceChange == 0)
        {
            return $this->useProrations = false;
        }
        else if ($priceChange < 0)
        {
            $isUpgrade = false;
            $isDowngrade = true;
        }
        else
        {
            $isUpgrade = true;
            $isDowngrade = false;
        }

        $result = null;
        foreach ($this->subscriptionProductModels as $subscriptionProduct)
        {
            $useProrationsForUpgrades = $subscriptionProduct->useProrationsForUpgrades();
            $useProrationsForDowngrades = $subscriptionProduct->useProrationsForDowngrades();

            if (($isUpgrade && $useProrationsForUpgrades) || ($isDowngrade && $useProrationsForDowngrades))
            {
                $useProrations = true;
            }
            else
            {
                $useProrations = false;
            }

            if ($result !== null && $useProrations !== $result)
            {
                // Two products in the cart have different proration configurations. In this case disable prorations.
                return $this->useProrations = false;
            }

            $result = $useProrations;
        }

        return $this->useProrations = (bool)$result;
    }

    public function getProductIDs()
    {
        $productIDs = [];
        $subscription = $this->getStripeObject();

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

    public function getProductID()
    {
        $productIDs = $this->getProductIDs();

        if (empty($productIDs))
            throw new GenericException("This subscription is not associated with any products.");

        return $productIDs[0];
    }

    public function getOrderID()
    {
        $subscription = $this->getStripeObject();

        if (isset($subscription->metadata->{"Order #"}))
        {
            return $subscription->metadata->{"Order #"};
        }

        return null;
    }

    public function getStripeAmount()
    {
        $subscription = $this->getStripeObject();

        if (empty($subscription->items->data[0]->price->unit_amount))
            throw new GenericException("This subscription has no price data.");

        // As of v3.3, subscriptions are combined in a single unit
        $stripeAmount = $subscription->items->data[0]->price->unit_amount;

        return $stripeAmount;
    }

    public function isCompositeSubscription()
    {
        $productIDs = $this->getProductIDs();

        return (count($productIDs) > 1);
    }

    public function getUpcomingInvoiceAfterUpdate($prorationTimestamp)
    {
        if (!$this->getStripeObject())
            throw new GenericException("No subscription specified.");

        /** @var \Stripe\Subscription $subscription */
        $subscription = $this->getStripeObject();

        if (empty($subscription->items->data[0]->price->id))
            throw new GenericException("This subscription has no price data.");

        // The subscription update will happen based on the quote items
        $quote = $this->quoteHelper->getQuote();
        $subscriptionDetails = $this->subscriptionsHelper->getSubscriptionFromQuote($quote);
        $subscriptionItems = $this->subscriptionsHelper->getSubscriptionItemsFromSubscriptionDetails($subscriptionDetails);

        $oldPriceId = $subscription->plan->id;
        $newPriceId = $subscriptionItems[0]['price'];

        $profile = $subscriptionDetails['profile'];
        $magentoAmount = $this->subscriptionsHelper->getSubscriptionTotalWithDiscountAdjustmentFromProfile($profile);
        $stripeAmount = $this->helper->convertMagentoAmountToStripeAmount($magentoAmount, $profile["currency"]);
        $newProductIds = explode(",", $subscriptionItems[0]["metadata"]["SubscriptionProductIDs"]);

        // See what the next invoice would look like with a price switch and proration set:
        /** @var \Stripe\SubscriptionItem $subscriptionItem */
        $subscriptionItem = $subscription->items->data[0];
        $items = [
          [
            'id' => $subscriptionItem->id,
            'price' => $newPriceId, # Switch to new price
          ],
        ];

        $params = [
          'customer' => $subscription->customer,
          'subscription' => $subscription->id,
          'subscription_items' => $items
        ];

        if ($this->useProrations($stripeAmount, $newProductIds))
        {
            $params['subscription_proration_date'] = $prorationTimestamp;
            $params['subscription_proration_behavior'] = "always_invoice";
        }
        else
        {
            $params['subscription_proration_behavior'] = "none";
        }

        $invoice = \Stripe\Invoice::upcoming($params);
        $invoice->oldPriceId = $oldPriceId;
        $invoice->newPriceId = $newPriceId;

        return $invoice;
    }

    public function performUpdate(\Magento\Payment\Model\InfoInterface $payment)
    {
        if (!$this->getStripeObject())
            throw new GenericException("No subscription to update from.");

        /** @var \Stripe\Subscription $subscription */
        $subscription = $this->getStripeObject();
        $latestInvoiceId = $subscription->latest_invoice;
        $originalOrderIncrementId = $this->subscriptionsHelper->getSubscriptionOrderID($subscription);

        if (empty($subscription->items->data))
        {
            throw new GenericException("There are no subscription items to update");
        }

        if (count($subscription->items->data) > 1)
        {
            throw new GenericException("Updating a subscription with multiple subscription items is not implemented.");
        }

        $order = $payment->getOrder();

        $quote = $this->quoteHelper->getQuote();
        $subscriptionDetails = $this->subscriptionsHelper->getSubscriptionFromOrder($order);
        $subscriptionItems = $this->subscriptionsHelper->getSubscriptionItemsFromSubscriptionDetails($subscriptionDetails);

        if (count($subscriptionItems) > 1)
        {
            throw new GenericException("Updating a subscription with multiple subscription items is not implemented.");
        }

        $subscriptionItems[0]['id'] = $subscription->items->data[0]->id;

        $params = [
            "items" => $subscriptionItems,
            "metadata" => $subscriptionItems[0]['metadata'] // There is only one item for the entire order,
        ];

        $metadata = $this->subscriptionsHelper->collectMetadataForSubscription($subscriptionDetails['profile']);
        $params["description"] = $this->orderHelper->getOrderDescription($order);
        $params["metadata"] = $metadata;

        $profile = $subscriptionDetails['profile'];
        $magentoAmount = $this->subscriptionsHelper->getSubscriptionTotalWithDiscountAdjustmentFromProfile($profile);
        $stripeAmount = $this->helper->convertMagentoAmountToStripeAmount($magentoAmount, $profile["currency"]);
        $newProductIds = explode(",", $subscriptionItems[0]["metadata"]["SubscriptionProductIDs"]);

        if ($this->useProrations($stripeAmount, $newProductIds))
        {
            $checkoutSession = $this->helper->getCheckoutSession();
            $subscriptionUpdateDetails = $checkoutSession->getSubscriptionUpdateDetails();

            if (!empty($subscriptionUpdateDetails['_data']['proration_timestamp']))
                $prorationTimestamp = $subscriptionUpdateDetails['_data']['proration_timestamp'];
            else
                $prorationTimestamp = time();

            $params["proration_behavior"] = "always_invoice";
            $params["proration_date"] = $prorationTimestamp;
        }
        else
        {
            $params["proration_behavior"] = "none";

            if ($this->changingPlanIntervals($subscription, $profile['interval'], $profile['interval_count']))
            {
                $params["trial_end"] = $subscription->current_period_end;
            }
        }

        $newPriceId = $subscriptionItems[0]['price'];

        try
        {
            $updatedSubscription = $this->config->getStripeClient()->subscriptions->update($subscription->id, $params);
            $this->setObject($updatedSubscription);
        }
        catch (\Stripe\Exception\InvalidRequestException $e)
        {
            $error = $e->getError();
            throw new \Magento\Framework\Exception\LocalizedException(__($error->message));
        }

        try
        {
            $subscriptionModel = $this->subscriptionsHelper->loadSubscriptionModelBySubscriptionId($updatedSubscription->id);
            $subscriptionModel->initFrom($updatedSubscription, $order);
            $subscriptionModel->setLastUpdated($this->dataHelper->dbTime());
            if (!$payment)
            {
                $subscriptionModel->setReorderFromQuoteId($quote->getId());
            }
            $subscriptionModel->save();
        }
        catch (\Stripe\Exception\InvalidRequestException $e)
        {
            $this->helper->logError($e->getMessage(), $e->getTraceAsString());
        }

        $originalOrder = $this->orderHelper->loadOrderByIncrementId($originalOrderIncrementId);
        if (!$originalOrder || !$originalOrder->getId())
        {
            throw new LocalizedException(__("Could not load the original order #%1 of this subscription.", $originalOrderIncrementId));
        }

        $payment->setIsTransactionPending(true);
        $invoice = null;
        if (!empty($updatedSubscription->latest_invoice))
        {
            /** @var \Stripe\Invoice @invoice */
            $invoice = $this->config->getStripeClient()->invoices->retrieve($updatedSubscription->latest_invoice, ['expand' => ['payment_intent', 'customer']]);
        }

        try
        {
            if ($invoice && $invoice->id != $latestInvoiceId && !empty($invoice->payment_intent))
            {
                $this->paymentIntentModel->setTransactionDetails($payment, $invoice->payment_intent);
                $payment->setAdditionalInformation("stripe_invoice_amount_paid", $invoice->amount_paid);
                $payment->setAdditionalInformation("stripe_invoice_currency", $invoice->currency);
                $payment->setIsTransactionPending(false);
            }
        }
        catch (\Exception $e)
        {
            $this->helper->logError("Could not set subscription transaction details: " . $e->getMessage());
        }

        $payment->setAdditionalInformation("is_subscription_update", true);
        $payment->setAdditionalInformation("subscription_id", $subscription->id);
        $payment->setAdditionalInformation("original_order_increment_id", $originalOrderIncrementId);
        $payment->setAdditionalInformation("customer_stripe_id", $subscription->customer);

        $subscriptionUpdateDetails = $this->helper->getCheckoutSession()->getSubscriptionUpdateDetails();

        $originalOrder->getPayment()->setAdditionalInformation("new_order_increment_id", $order->getIncrementId());
        $previousSubscriptionAmount = $this->subscriptionsHelper->formatInterval(
            $subscription->plan->amount,
            $subscription->plan->currency,
            $subscription->plan->interval_count,
            $subscription->plan->interval
        );
        $newSubscriptionAmount = $this->subscriptionsHelper->formatInterval(
            $updatedSubscription->plan->amount,
            $updatedSubscription->plan->currency,
            $updatedSubscription->plan->interval_count,
            $updatedSubscription->plan->interval
        );
        $originalOrder->getPayment()->setAdditionalInformation("previous_subscription_amount", (string)$previousSubscriptionAmount);
        $originalOrder->getPayment()->setAdditionalInformation("new_subscription_amount", (string)$newSubscriptionAmount);
        $payment->setAdditionalInformation("previous_subscription_amount", (string)$previousSubscriptionAmount);
        $payment->setAdditionalInformation("new_subscription_amount", (string)$newSubscriptionAmount);
        $this->orderHelper->saveOrder($originalOrder);

        $this->helper->getCheckoutSession()->unsSubscriptionUpdateDetails();

        if (!empty($invoice->customer->balance) && $invoice->customer->balance < 0)
        {
            $balance = abs($invoice->customer->balance);
            $message = __("Your account has a total credit of %1, which will be used to offset future subscription payments.", $this->helper->formatStripePrice($balance, $invoice->currency));
            $payment->setAdditionalInformation("stripe_balance", $balance);

            // Also add a note to the order
            $order->addStatusToHistory($status = null, $message, $isCustomerNotified = true);
        }

        return $updatedSubscription;
    }

    public function getFormattedAmount()
    {
        $subscription = $this->getStripeObject();

        return $this->helper->formatStripePrice($subscription->plan->amount, $subscription->plan->currency);
    }

    public function getFormattedBilling()
    {
        $subscription = $this->getStripeObject();

        return $this->subscriptionsHelper->getInvoiceAmount($subscription) . " " .
                $this->subscriptionsHelper->formatDelivery($subscription) . " " .
                $this->subscriptionsHelper->formatLastBilled($subscription);
    }

    public function addToCart()
    {
        $subscriptionProductModel = $this->getSubscriptionProductModel();

        if (!$subscriptionProductModel || !$subscriptionProductModel->getProductId())
            throw new LocalizedException(__("Could not load subscription product."));

        $subscription = $this->getStripeObject();
        $order = $this->getOrder();
        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->quoteHelper->getQuote();
        $quote->removeAllItems();
        $quote->removeAllAddresses();
        $extensionAttributes = $quote->getExtensionAttributes();
        $extensionAttributes->setShippingAssignments([]);

        $orderItem = $this->getOrderItem();
        $product = $orderItem->getProduct();
        $buyRequest = $this->dataHelper->getConfigurableProductBuyRequest($orderItem);

        if (!$buyRequest)
            throw new LocalizedException(__("Could not load the original order items."));

        unset($buyRequest['uenc']);
        unset($buyRequest['item']);
        foreach ($buyRequest as $key => $value)
        {
            if (empty($value))
                unset($buyRequest[$key]);
        }

        $request = $this->dataObjectFactory->create($buyRequest);
        $result = $quote->addProduct($product, $request);
        if (is_string($result))
            throw new LocalizedException(__($result));

        $quote->getShippingAddress()->setCollectShippingRates(false);
        $quote->setTotalsCollectedFlag(false)->collectTotals();
        $this->quoteHelper->saveQuote($quote);

        // For some reason (possibly a Magento bug), quote items do not have an ID even though the quote is saved
        // This creates a problem down the line when trying to change customizable options of the quote items
        foreach ($quote->getAllItems() as $item)
        {
            // Generate quote item IDs
            $item->save();
        }

        try
        {
            if (!$order->getIsVirtual() && !$quote->getIsVirtual() && $order->getShippingMethod())
            {
                $shippingMethod = $order->getShippingMethod();
                $shippingAddress = $quote->getShippingAddress();
                $shippingAddress->addData($order->getShippingAddress()->getData());
                $shippingAddress->setCollectShippingRates(true)
                        ->collectShippingRates()
                        ->setShippingMethod($order->getShippingMethod())
                        ->save();
            }
        }
        catch (\Exception $e)
        {
            // The shipping address or method may not be available, ignore in this case
        }

        return $this;
    }

    private function changingPlanIntervals($subscription, $interval, $intervalCount)
    {
        if ($subscription->plan->interval != $interval)
            return true;

        if ($subscription->plan->interval_count != $intervalCount)
            return true;

        return false;
    }
}
