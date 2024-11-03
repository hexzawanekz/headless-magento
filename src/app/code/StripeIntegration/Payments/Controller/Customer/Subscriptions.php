<?php

namespace StripeIntegration\Payments\Controller\Customer;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\TemporaryState\CouldNotSaveException;
use StripeIntegration\Payments\Exception\GenericException;

class Subscriptions implements ActionInterface
{
    private $resultPageFactory;
    private $helper;
    private $subscriptionsHelper;
    private $dataHelper;
    private $order;
    private $stripeCustomer;
    private $subscriptionFactory;
    private $subscriptionProductFactory;
    private $stripeSubscriptionScheduleFactory;
    private $stripeSubscriptionFactory;
    private $resultFactory;
    private $customerSession;
    private $request;
    private $quoteHelper;
    private $orderHelper;
    private $productHelper;

    public function __construct(
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Customer\Model\Session $session,
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Helper\Quote $quoteHelper,
        \StripeIntegration\Payments\Helper\Order $orderHelper,
        \StripeIntegration\Payments\Helper\Subscriptions $subscriptionsHelper,
        \StripeIntegration\Payments\Helper\Data $dataHelper,
        \StripeIntegration\Payments\Helper\Product $productHelper,
        \StripeIntegration\Payments\Model\SubscriptionFactory $subscriptionFactory,
        \StripeIntegration\Payments\Model\SubscriptionProductFactory $subscriptionProductFactory,
        \StripeIntegration\Payments\Model\Stripe\SubscriptionScheduleFactory $stripeSubscriptionScheduleFactory,
        \StripeIntegration\Payments\Model\Stripe\SubscriptionFactory $stripeSubscriptionFactory,
        \Magento\Sales\Model\Order $order,
        \Magento\Framework\Controller\ResultFactory $resultFactory,
        \Magento\Framework\App\RequestInterface $request
    )
    {
        $this->resultPageFactory = $resultPageFactory;
        $this->helper = $helper;
        $this->quoteHelper = $quoteHelper;
        $this->orderHelper = $orderHelper;
        $this->subscriptionsHelper = $subscriptionsHelper;
        $this->dataHelper = $dataHelper;
        $this->productHelper = $productHelper;
        $this->order = $order;
        $this->stripeCustomer = $helper->getCustomerModel();
        $this->subscriptionFactory = $subscriptionFactory;
        $this->subscriptionProductFactory = $subscriptionProductFactory;
        $this->stripeSubscriptionScheduleFactory = $stripeSubscriptionScheduleFactory;
        $this->stripeSubscriptionFactory = $stripeSubscriptionFactory;
        $this->resultFactory = $resultFactory;
        $this->customerSession = $session;
        $this->request = $request;
    }

    public function execute()
    {
        if (!$this->customerSession->isLoggedIn())
            return $this->redirect('customer/account/login');

        $params = $this->getRequest()->getParams();

        if (isset($params['viewOrder']))
            return $this->viewOrder($params['viewOrder']);
        else if (isset($params['edit']))
            return $this->editSubscription($params['edit']);
        else if (isset($params['updateSuccess']))
            return $this->onUpdateSuccess();
        else if (isset($params['updateCancel']))
            return $this->onUpdateCancel();
        else if (isset($params['cancel']))
            return $this->cancelSubscription($params['cancel']);
        else if (isset($params['changeCard']))
            return $this->changeCard($params['changeCard'], $params['subscription_card']);
        else if (isset($params['changeShipping']))
            return $this->changeShipping($params['changeShipping']);
        else if (isset($params['reactivate']))
            return $this->reactivateSubscription($params['reactivate']);
        else if (!empty($params))
            return $this->redirect('stripe/customer/subscriptions');

        return $this->resultPageFactory->create();
    }

    protected function onUpdateCancel()
    {
        $this->subscriptionsHelper->cancelSubscriptionUpdate();
        return $this->redirect('stripe/customer/subscriptions');
    }

    protected function onUpdateSuccess()
    {
        $this->helper->addSuccess(__("The subscription has been updated successfully."));
        return $this->redirect('stripe/customer/subscriptions');
    }

    protected function  viewOrder($incrementOrderId)
    {
        $this->order->loadByIncrementId($incrementOrderId);

        if ($this->order->getId())
            return $this->redirect('sales/order/view/', ['order_id' => $this->order->getId()]);
        else
        {
            $this->helper->addError("Order #$incrementOrderId could not be found!");
            return $this->redirect('stripe/customer/subscriptions');
        }
    }

    protected function cancelSubscription($subscriptionId)
    {
        try
        {
            if (!$this->stripeCustomer->getStripeId())
            {
                $this->helper->addError(__("Sorry, the subscription could not be canceled. Please contact us for more help."));
                $this->helper->logError("Could not load customer account for subscription with ID $subscriptionId!");
            }
            else
            {
                $subscriptionModel = $this->subscriptionFactory->create()->fromSubscriptionId($subscriptionId);
                $this->verifySubscription($subscriptionModel->getStripeObject());
                $subscriptionModel->cancel();
                $this->helper->addSuccess(__("The subscription has been canceled successfully!"));
            }
        }
        catch (LocalizedException $e)
        {
            $this->helper->addError($e->getMessage());
            $this->helper->logError("Could not cancel subscription with ID $subscriptionId: " . $e->getMessage());
        }
        catch (\Exception $e)
        {
            $this->helper->addError(__("Sorry, the subscription could not be canceled. Please contact us for more help."));
            $this->helper->logError("Could not cancel subscription with ID $subscriptionId: " . $e->getMessage(), $e->getTraceAsString());
        }

        return $this->redirect('stripe/customer/subscriptions');
    }

    protected function changeCard($subscriptionId, $cardId)
    {
        $subscription = $this->subscriptionFactory->create()->fromSubscriptionId($subscriptionId)->getStripeObject();
        $this->verifySubscription($subscription);

        if (!$this->stripeCustomer->getStripeId())
        {
            $this->helper->addError("Sorry, the subscription could not be updated. Please contact us for more help.");
            $this->helper->logError("Could not load customer account for subscription with ID $subscriptionId!");
        }
        else
        {
            \Stripe\Subscription::update($subscriptionId, ['default_payment_method' => $cardId]);
            $this->helper->addSuccess(__("The subscription has been updated."));
        }

        return $this->redirect('stripe/customer/subscriptions');
    }

    private function verifySubscription($subscription)
    {
        if (!$subscription || !$subscription->customer || $subscription->customer != $this->stripeCustomer->getStripeId())
        {
            throw new CouldNotSaveException(__("An invalid subscription has been specified."));
        }
    }

    protected function editSubscription($subscriptionId)
    {
        try
        {
            if (!$this->stripeCustomer->getStripeId())
                throw new LocalizedException(__("Could not load customer account."));

            $subscriptionId = $this->getRequest()->getParam("edit", null);
            if (!$subscriptionId)
                throw new LocalizedException(__("Invalid subscription ID."));

            /** @var \StripeIntegration\Payments\Model\Stripe\Subscription $stripeSubscriptionModel */
            $stripeSubscriptionModel = $this->stripeSubscriptionFactory->create()->fromSubscriptionId($subscriptionId);
            $this->verifySubscription($stripeSubscriptionModel->getStripeObject());
            $order = $stripeSubscriptionModel->getOrder();

            if (!$order || !$order->getId())
                throw new LocalizedException(__("Could not load order for this subscription."));

            $stripeSubscriptionModel->addToCart();

            $this->setSubscriptionUpdateDetails($stripeSubscriptionModel->getStripeObject(), [ $stripeSubscriptionModel->getProductId() ]);
            $product = $stripeSubscriptionModel->getOrderItem()->getProduct();
            $quoteItem = $this->quoteHelper->getQuote()->getItemByProduct($product);

            if (!$quoteItem) {
                throw new LocalizedException(__("Could not load the original order items."));
            }
            $quoteItemId = $quoteItem->getId();

            return $this->redirect('checkout/cart/configure', ['id' => $quoteItemId, 'product_id' => $product->getId()]);
        }
        catch (LocalizedException $e)
        {
            $this->helper->addError($e->getMessage());
            $this->helper->logError("Could not update subscription with ID $subscriptionId: " . $e->getMessage(), $e->getTraceAsString());
        }
        catch (\Exception $e)
        {
            $this->helper->addError(__("Sorry, the subscription could not be updated. Please contact us for more help."));
            $this->helper->logError("Could not update subscription with ID $subscriptionId: " . $e->getMessage(), $e->getTraceAsString());
        }

        return $this->redirect('stripe/customer/subscriptions');
    }

    protected function changeShipping($subscriptionId)
    {
        try
        {
            if (!$this->stripeCustomer->getStripeId())
                throw new LocalizedException(__("Could not load customer account."));

            if (!$subscriptionId)
                throw new LocalizedException(__("Invalid subscription ID."));

            $subscription = $this->stripeSubscriptionFactory->create()->fromSubscriptionId($subscriptionId)->getStripeObject();
            $this->verifySubscription($subscription);
            $orderIncrementId = $this->subscriptionsHelper->getSubscriptionOrderID($subscription);
            if (!$orderIncrementId)
                throw new LocalizedException(__("This subscription is not associated with an order."));

            $order = $this->orderHelper->loadOrderByIncrementId($orderIncrementId);

            if (!$order)
                throw new LocalizedException(__("Could not load order for this subscription."));

            $quote = $this->quoteHelper->getQuote();
            $quote->removeAllItems();
            $quote->removeAllAddresses();
            $extensionAttributes = $quote->getExtensionAttributes();
            $extensionAttributes->setShippingAssignments([]);

            $productIds = $this->subscriptionsHelper->getSubscriptionProductIDs($subscription);
            $items = $order->getItemsCollection();
            foreach ($items as $item)
            {
                $subscriptionProductModel = $this->subscriptionProductFactory->create()->fromOrderItem($item);

                if ($subscriptionProductModel->isSubscriptionProduct() &&
                    $subscriptionProductModel->getIsSalable() &&
                    in_array($subscriptionProductModel->getProductId(), $productIds)
                    )
                {
                    $product = $subscriptionProductModel->getProduct();

                    if ($item->getParentItem() && $item->getParentItem()->getProductType() == "configurable")
                    {
                        $item = $item->getParentItem();
                        try
                        {
                            $product = $this->productHelper->getProduct($item->getProductId());

                            if (!$product->getIsSalable())
                                continue;
                        }
                        catch (\Exception $e)
                        {
                            continue;
                        }
                    }

                    $request = $this->dataHelper->getBuyRequest($item);
                    $result = $quote->addProduct($product, $request);
                    if (is_string($result))
                        throw new LocalizedException(__($result));
                }
            }

            if (!$quote->hasItems())
                throw new LocalizedException(__("Sorry, this subscription product is currently unavailable."));

            $this->setSubscriptionUpdateDetails($subscription, $productIds);

            $quote->getShippingAddress()->setCollectShippingRates(false);
            $quote->setTotalsCollectedFlag(false)->collectTotals();
            $this->quoteHelper->saveQuote($quote);
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

            return $this->redirect('checkout');
        }
        catch (LocalizedException $e)
        {
            $this->helper->addError($e->getMessage());
            $this->helper->logError("Could not update subscription with ID $subscriptionId: " . $e->getMessage());
        }
        catch (\Exception $e)
        {
            $this->helper->addError(__("Sorry, the subscription could not be updated. Please contact us for more help."));
            $this->helper->logError("Could not update subscription with ID $subscriptionId: " . $e->getMessage(), $e->getTraceAsString());
        }

        return $this->redirect('stripe/customer/subscriptions');
    }

    public function setSubscriptionUpdateDetails($subscription, $productIds)
    {
        // Last billed
        $startDate = $subscription->created;
        $date = $subscription->current_period_start;

        if ($startDate > $date)
        {
            $lastBilled = null;
        }
        else
        {
            $day = date("j", $date);
            $sup = date("S", $date);
            $month = date("F", $date);
            $year = date("y", $date);

            $lastBilled =  __("%1<sup>%2</sup>&nbsp;%3&nbsp;%4", $day, $sup, $month, $year);
        }

        // Next billing date
        $periodEnd = $subscription->current_period_end;
        if (!empty($subscription->schedule))
        {
            $schedule = $this->stripeSubscriptionScheduleFactory->create()->load($subscription->schedule);
            $nextBillingTimestamp = $schedule->getNextBillingTimestamp();

            if ($nextBillingTimestamp)
            {
                $periodEnd = $nextBillingTimestamp;
            }
        }
        $day = date("j", $periodEnd);
        $sup = date("S", $periodEnd);
        $month = date("F", $periodEnd);
        $year = date("y", $periodEnd);
        $nextBillingDate = __("%1<sup>%2</sup>&nbsp;%3&nbsp;%4", $day, $sup, $month, $year);

        $checkoutSession = $this->helper->getCheckoutSession();
        $checkoutSession->setSubscriptionUpdateDetails([
            "_data" => [
                "subscription_id" => $subscription->id,
                "original_order_increment_id" => $this->subscriptionsHelper->getSubscriptionOrderID($subscription),
                "product_ids" => $productIds,
                "current_period_end" => $periodEnd,
                "current_period_start" => $subscription->current_period_start,
                "proration_timestamp"=> time()
            ],
            "current_price_label" => $this->subscriptionsHelper->getInvoiceAmount($subscription) . " " . $this->subscriptionsHelper->formatDelivery($subscription),
            "last_billed_label" => $lastBilled,
            "next_billing_date" => $nextBillingDate
        ]);
    }

    protected function reactivateSubscription($subscriptionId)
    {
        $subscriptionModel = $this->subscriptionFactory->create()->fromSubscriptionId($subscriptionId);
        $this->verifySubscription($subscriptionModel->getStripeObject());

        if (!$this->stripeCustomer->getStripeId())
        {
            $this->helper->addError(__("Could not load customer account."));
        }
        else if (!$subscriptionId)
        {
            $this->helper->addError(__("Invalid subscription ID."));
        }
        else
        {
            try {

                $subscriptionModel->reactivate();
                $this->helper->addSuccess(__("The subscription has been reactivated."));
            }
            catch (GenericException | LocalizedException $e)
            {
                $this->helper->addError($e->getMessage());
                $this->helper->logError("Could not reactivate the subscription with ID $subscriptionId: " . $e->getMessage());
            }
            catch (\Exception $e) {
                $this->helper->addError(__("Sorry, there was a problem reactivating the subscription"));
                $this->helper->logError("Unable to reactivate the subscription $subscriptionId: " . $e->getMessage(), $e->getTraceAsString());
            }
        }

        return $this->redirect('stripe/customer/subscriptions');
    }

    public function redirect($url, array $params = [])
    {
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $redirect->setPath($url, $params);

        return $redirect;
    }

    public function getRequest()
    {
        return $this->request;
    }
}
