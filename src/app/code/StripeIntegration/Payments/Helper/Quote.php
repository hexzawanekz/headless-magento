<?php

namespace StripeIntegration\Payments\Helper;

class Quote
{
    // $quoteId is set right before the order is placed from inside Plugin/Sales/Model/Service/OrderService,
    // as the GraphQL flow may place an order without a loaded quote. Used for loading the quote later.
    public $quoteId = null;

    private $quotesCache = [];

    private $backendSessionQuote;
    private $checkoutSession;
    private $quoteRepository;
    private $areaCodeHelper;
    private $productHelper;
    private $subscriptionProductFactory;
    private $quoteFactory;

    public function __construct(
        \Magento\Backend\Model\Session\Quote $backendSessionQuote,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \StripeIntegration\Payments\Helper\AreaCode $areaCodeHelper,
        \StripeIntegration\Payments\Helper\Product $productHelper,
        \StripeIntegration\Payments\Model\SubscriptionProductFactory $subscriptionProductFactory
    )
    {
        $this->backendSessionQuote = $backendSessionQuote;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->areaCodeHelper = $areaCodeHelper;
        $this->productHelper = $productHelper;
        $this->subscriptionProductFactory = $subscriptionProductFactory;
        $this->quoteFactory = $quoteFactory;
    }

    // This method is not inside the subscriptions helper to avoid circular dependencies between Model/Config and other classes.
    public function hasSubscriptions(?\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if (!$quote)
            $quote = $this->getQuote();

        $quoteId = $quote->getId();

        if ($quoteId)
        {
            if (isset($this->quotesCache[$quoteId]))
            {
                if ($this->quotesCache[$quoteId]->getHasSubscriptions() !== null)
                {
                    return $this->quotesCache[$quoteId]->getHasSubscriptions();
                }
            }
            else
            {
                $this->quotesCache[$quoteId] = $quote;
            }
        }

        $items = $quote->getAllItems();
        $hasSubscriptions = $this->hasSubscriptionsIn($items);
        $quote->setHasSubscriptions($hasSubscriptions);

        return $hasSubscriptions;
    }

    public function hasSubscriptionsIn($quoteItems)
    {
        foreach ($quoteItems as $item)
        {
            $subscriptionProductModel = $this->subscriptionProductFactory->create()->fromQuoteItem($item);
            if ($subscriptionProductModel->isSubscriptionProduct())
            {
                return true;
            }
        }

        return false;
    }

    public function getQuote($quoteId = null): \Magento\Quote\Api\Data\CartInterface
    {
        // Admin area new order page
        if ($this->areaCodeHelper->isAdmin())
            return $this->getBackendSessionQuote();

        // Front end checkout
        $quote = $this->getSessionQuote();

        // API Request
        if (empty($quote) || !is_numeric($quote->getGrandTotal()))
        {
            try
            {
                if ($quoteId)
                    $quote = $this->quoteRepository->get($quoteId);
                else if ($this->quoteId) {
                    $quote = $this->quoteRepository->get($this->quoteId);
                }
            }
            catch (\Exception $e)
            {

            }
        }

        return $quote;
    }

    public function getQuoteDescription($quote)
    {
        if ($quote->getCustomerIsGuest())
            $customerName = $quote->getBillingAddress()->getName();
        else
            $customerName = $quote->getCustomerName();

        if (!empty($customerName))
            $description = __("Cart %1 by %2", $quote->getId(), $customerName);
        else
            $description = __("Cart %1", $quote->getId());

        return $description;
    }

    public function loadQuoteById($quoteId)
    {
        if (!is_numeric($quoteId))
            return null;

        if (!empty($this->quotesCache[$quoteId]))
            return $this->quotesCache[$quoteId];

        $this->quotesCache[$quoteId] = $this->quoteFactory->create()->load($quoteId);

        return $this->quotesCache[$quoteId];
    }

    private function getBackendSessionQuote()
    {
        return $this->backendSessionQuote->getQuote();
    }

    private function getSessionQuote()
    {
        return $this->checkoutSession->getQuote();
    }

    public function saveQuote($quote = null)
    {
        if (!$quote)
            $quote = $this->getQuote();

        $this->quoteRepository->save($quote);

        return $quote;
    }

    /**
     * Add product to shopping cart (quote)
     */
    public function addProduct($productId, array $requestInfo = null)
    {
        if (!$productId)
            throw new \Magento\Framework\Exception\LocalizedException(__('The product does not exist.'));

        $request = new \Magento\Framework\DataObject($requestInfo);
        try
        {
            $product = $this->productHelper->getProduct($productId);
            $result = $this->getQuote()->addProduct($product, $request);
        }
        catch (\Magento\Framework\Exception\LocalizedException $e)
        {
            $this->checkoutSession->setUseNotice(false);
            $result = $e->getMessage();
        }
        /**
         * String we can get if prepare process has error
         */
        if (is_string($result)) {
            throw new \Magento\Framework\Exception\LocalizedException(__($result));
        }

        $this->checkoutSession->setLastAddedProductId($productId);
        return $result;
    }

    public function removeItem($itemId)
    {
        $item = $this->getQuote()->removeItem($itemId);

        if ($item->getHasError()) {
            throw new \Magento\Framework\Exception\LocalizedException(__($item->getMessage()));
        }

        return $this;
    }

    public function isProductInCart($productId)
    {
        $quote = $this->getQuote();
        $items = $quote->getAllItems();
        foreach ($items as $item)
        {
            if ($item->getProductId() == $productId)
                return true;
        }

        return false;
    }

    /**
     * Adding products to cart by ids
     */
    public function addProductsByIds(array $productIds)
    {
        foreach ($productIds as $productId) {
            $this->addProduct($productId);
        }

        return $this;
    }

    public function isMultiShipping($quote = null)
    {
        if (empty($quote))
            $quote = $this->getQuote();

        if (empty($quote))
            return false;

        return $quote->getIsMultiShipping();
    }

    public function clearCache()
    {
        $this->quotesCache = [];
    }

    public function reloadQuote($quote)
    {
        $quote = $this->quoteRepository->get($quote->getId());
        $this->quotesCache[$quote->getId()] = $quote;
        return $quote;
    }

    public function hasSubscriptionsWithStartDate($quote = null)
    {
        if (!$quote)
            $quote = $this->getQuote();

        $items = $quote->getAllItems();
        foreach ($items as $item)
        {
            $subscriptionProductModel = $this->subscriptionProductFactory->create()->fromQuoteItem($item);
            if ($subscriptionProductModel->isSubscriptionProduct() &&
                $subscriptionProductModel->hasStartDate()
            )
            {
                return true;
            }
        }

        return false;
    }

    public function hasTrialSubscriptionsIn($quoteItems)
    {
        foreach ($quoteItems as $item)
        {
            $subscriptionProductModel = $this->subscriptionProductFactory->create()->fromQuoteItem($item);
            if ($subscriptionProductModel->isSubscriptionProduct() && $subscriptionProductModel->hasTrialPeriod())
            {
                return true;
            }
        }

        return false;
    }

    public function hasOnlyTrialSubscriptions($quote = null)
    {
        if (!$quote)
            $quote = $this->getQuote();

        if (!$quote || !$quote->getId())
            return false;

        $items = $quote->getAllItems();
        $trialSubscriptions = 0;

        foreach ($items as $item)
        {
            $subscriptionProductModel = $this->subscriptionProductFactory->create()->fromQuoteItem($item);
            if (!$subscriptionProductModel->isSubscriptionProduct())
                return false;

            if (!$subscriptionProductModel->hasTrialPeriod())
                return false;

            $trialSubscriptions++;
        }

        return $trialSubscriptions > 0;
    }

    public function getNonBillableSubscriptionItems($items)
    {
        $nonBillableItems = [];

        foreach ($items as $item)
        {
            $subscriptionProductModel = $this->subscriptionProductFactory->create()->fromQuoteItem($item);

            if (!$subscriptionProductModel->isSubscriptionProduct())
                continue;

            if (!$subscriptionProductModel->hasZeroInitialOrderPrice())
                continue;

            if ($item->getParentItem()) // Bundle and configurable subscriptions
            {
                $item = $item->getParentItem();
                $nonBillableItems[] = $item;

                // Get all child products
                foreach ($items as $item2)
                {
                    if ($item2->getParentItemId() == $item->getId())
                        $nonBillableItems[] = $item2;
                }
            }
            else
            {
                $nonBillableItems[] = $item;
            }
        }

        return $nonBillableItems;
    }

    // Checks if the quote has a 100% discount rule, and that the discount will eventually expire
    public function hasFullyDiscountedSubscriptions($quote)
    {
        $items = $quote->getAllItems();

        foreach ($items as $item)
        {
            $subscriptionProductModel = $this->subscriptionProductFactory->create()->fromQuoteItem($item);

            if (!$subscriptionProductModel->isSubscriptionProduct())
            {
                continue;
            }

            if ($item->getBasePrice() > 0 && $item->getBasePrice() <= $item->getBaseDiscountAmount())
            {
                return true;
            }
        }

        return false;
    }

    public function reCollectTotals($quote)
    {
        $shippingMethod = null;
        $quote->getBillingAddress()->unsetData('cached_items_all');
        $quote->getBillingAddress()->unsetData('cached_items_nominal');
        $quote->getBillingAddress()->unsetData('cached_items_nonnominal');
        if (!$quote->getIsVirtual())
        {
            $shippingMethod = $quote->getShippingAddress()->getShippingMethod();
            $quote->getShippingAddress()->unsetData('cached_items_all');
            $quote->getShippingAddress()->unsetData('cached_items_nominal');
            $quote->getShippingAddress()->unsetData('cached_items_nonnominal');
            $quote->getShippingAddress()->setCollectShippingRates(true);
        }
        foreach ($quote->getAllItems() as $item)
        {
            $item->setTaxCalculationPrice(null);
            $item->setBaseTaxCalculationPrice(null);
        }
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();

        if ($shippingMethod)
        {
            // We restore it because when the shipping rates are collected, the shipping method is reset
            $quote->getShippingAddress()->setShippingMethod($shippingMethod);
        }
    }

    public function removeSubscriptions(\Magento\Quote\Api\Data\CartInterface $quote)
    {
        $removed = false;
        $items = $quote->getAllItems();
        foreach ($items as $item)
        {
            $subscriptionProductModel = $this->subscriptionProductFactory->create()->fromQuoteItem($item);
            if ($subscriptionProductModel->isSubscriptionProduct())
            {
                if ($item->getParentItem())
                {
                    $quote->removeItem($item->getParentItem()->getId());
                    $removed = true;
                }
                else
                {
                    $quote->removeItem($item->getId());
                    $removed = true;
                }
            }
        }

        return $removed;
    }
}
