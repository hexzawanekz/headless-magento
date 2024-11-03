<?php

namespace StripeIntegration\Payments\Model;

use StripeIntegration\Payments\Exception\InvalidSubscriptionProduct;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Directory\Model\CurrencyFactory;

class SubscriptionProduct
{
    private $product = null;
    private $subscriptionDetails = null;
    private $subscriptionHelper;
    private $storeManager;
    private $currencyFactory;
    private $productHelper;
    private $checkoutSessionHelper;

    public function __construct(
        \StripeIntegration\Payments\Helper\Product $productHelper,
        \StripeIntegration\Payments\Helper\Subscriptions $subscriptionHelper,
        \StripeIntegration\Payments\Helper\CheckoutSession $checkoutSessionHelper,
        StoreManagerInterface $storeManager,
        CurrencyFactory $currencyFactory
    )
    {
        $this->productHelper = $productHelper;
        $this->subscriptionHelper = $subscriptionHelper;
        $this->checkoutSessionHelper = $checkoutSessionHelper;
        $this->storeManager = $storeManager;
        $this->currencyFactory = $currencyFactory;
    }

    public function fromQuoteItem($item)
    {
        if (empty($item) || !$item->getProduct())
            throw new InvalidSubscriptionProduct("Invalid quote item.");

        $product = $this->productHelper->getProduct($item->getProduct()->getId());
        if ($this->_isSubscriptionProduct($product))
        {
            $this->product = $product;
            $this->subscriptionDetails = $this->subscriptionHelper->getSubscriptionOptionDetails($product->getId());
        }

        return $this;
    }

    public function fromOrderItem($orderItem)
    {
        if (empty($orderItem) || !$orderItem->getProductId())
            throw new InvalidSubscriptionProduct("Invalid order item.");

        $product = $this->productHelper->getProduct($orderItem->getProductId());
        if ($this->_isSubscriptionProduct($product))
        {
            $this->product = $product;
            $this->subscriptionDetails = $this->subscriptionHelper->getSubscriptionOptionDetails($product->getId());
        }

        return $this;
    }

    public function fromProductId($productId)
    {
        if (empty($productId))
            throw new InvalidSubscriptionProduct("Invalid product ID.");

        $product = $this->productHelper->getProduct($productId);
        if ($this->_isSubscriptionProduct($product))
        {
            $this->product = $product;
            $this->subscriptionDetails = $this->subscriptionHelper->getSubscriptionOptionDetails($product->getId());
        }

        return $this;
    }

    public function getIsSalable()
    {
        return $this->getProduct()->getIsSalable();
    }

    public function hasStartDate()
    {
        if (!$this->product)
            return false;

        $subscriptionOptions = $this->subscriptionDetails;

        if (!$subscriptionOptions ||
            empty($subscriptionOptions->getStartOnSpecificDate()) ||
            empty($subscriptionOptions->getStartDate()) ||
            !is_string($subscriptionOptions->getStartDate()) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $subscriptionOptions->getStartDate()))
        {
            return false;
        }

        return true;
    }

    public function startsOnOrderDate()
    {
        return $this->hasStartDate() && $this->subscriptionDetails->getFirstPayment() == "on_order_date";
    }

    public function startsOnStartDate()
    {
        return $this->hasStartDate() && $this->subscriptionDetails->getFirstPayment() == "on_start_date";
    }

    public function hasZeroInitialOrderPrice()
    {
        if ($this->hasTrialPeriod())
        {
            return true;
        }

        if ($this->startsOnStartDate() && !$this->startDateIsToday())
        {
            return true;
        }

        if ($this->checkoutSessionHelper->isSubscriptionUpdate())
        {
            return true;
        }

        return false;
    }

    public function startDateIsToday()
    {
        if (!$this->hasStartDate())
            return false;

        $startDate = strtotime($this->subscriptionDetails->getStartDate());

        return (date("d") == date("d", $startDate));
    }

    public function getProduct()
    {
        if (!$this->product)
        {
            throw new InvalidSubscriptionProduct("Invalid subscription product.");
        }

        return $this->product;
    }

    public function getProductId()
    {
        if (!$this->product)
        {
            throw new InvalidSubscriptionProduct("Invalid subscription product.");
        }

        return $this->product->getId();
    }

    public function getTrialDays()
    {
        $product = $this->product;

        if (!$product)
        {
            throw new InvalidSubscriptionProduct("Invalid subscription product.");
        }

        if (!$this->subscriptionDetails)
        {
            throw new InvalidSubscriptionProduct("Subscription details not found.");
        }

        if ($this->hasStartDate())
            return null;

        $trialDays = $this->subscriptionDetails->getSubTrial();
        if (!$trialDays || !is_numeric($trialDays) || $trialDays < 1)
            return null;

        return $trialDays;
    }

    public function hasTrialPeriod()
    {
        $trialDays = $this->getTrialDays();
        if (!is_numeric($trialDays))
            return false;

        return true;
    }

    public function canChangeShipping()
    {
        if ($this->product && $this->product->getTypeId() == "simple")
        {
            return true;
        }

        return false;
    }

    public function isSubscriptionProduct()
    {
        return !empty($this->product);
    }

    private function _isSubscriptionProduct(
        \Magento\Catalog\Api\Data\ProductInterface $product
    )
    {
        if (!$product || !$product->getId())
            return false;

        if (!in_array($product->getTypeId(), ["simple", "virtual"]))
            return false;

        $subscriptionOptionDetails = $this->subscriptionHelper->getSubscriptionOptionDetails($product->getId());

        if (!$subscriptionOptionDetails || !$subscriptionOptionDetails->getSubEnabled()) {
            return false;
        }

        $interval = $subscriptionOptionDetails->getSubInterval();
        $intervalCount = (int)$subscriptionOptionDetails->getSubIntervalCount();

        if (!$interval)
            return false;

        if ($intervalCount && !is_numeric($intervalCount))
            return false;

        if ($intervalCount < 0)
            return false;

        return true;
    }

    public function isSimpleProduct()
    {
        $product = $this->product;

        if (!$product || !$product->getId())
        {
            return false;
        }

        if ($product->getTypeId() != "simple")
        {
            return false;
        }

        return true;
    }

    public function isVirtualProduct()
    {
        $product = $this->product;

        if (!$product || !$product->getId())
        {
            return false;
        }

        if ($product->getTypeId() != "virtual")
        {
            return false;
        }

        return true;
    }

    public function getSubscriptionDetails()
    {
        if (!$this->subscriptionDetails)
        {
            throw new InvalidSubscriptionProduct("Subscription details not found.");
        }

        return $this->subscriptionDetails;
    }

    public function canChangeSubscription()
    {
        return ($this->getSubscriptionDetails()->getUpgradesDowngrades());
    }

    public function useProrationsForUpgrades()
    {
        if (!$this->canChangeSubscription())
            return false;

        return ($this->getSubscriptionDetails()->getProrateUpgrades());
    }

    public function useProrationsForDowngrades()
    {
        if (!$this->canChangeSubscription())
            return false;

        return ($this->getSubscriptionDetails()->getProrateDowngrades());
    }

    public function getFormattedInterval()
    {
        $subscriptionDetails = $this->getSubscriptionDetails();

        $intervalCount = $subscriptionDetails->getSubIntervalCount();
        $interval = ucfirst($subscriptionDetails->getSubInterval());
        $plural = ($intervalCount > 1 ? 's' : '');

        return "$intervalCount $interval$plural";
    }

    public function getBaseInitialFeeAmount()
    {
        $subscriptionOptionDetails = $this->getSubscriptionDetails();

        $subInitialFee = $subscriptionOptionDetails->getSubInitialFee();

        if (!is_numeric($subInitialFee) || $subInitialFee < 0)
            return 0;

        return $subInitialFee;
    }

    public function getInterval()
    {
        if (!$this->subscriptionDetails)
        {
            throw new InvalidSubscriptionProduct("Subscription details not found.");
        }

        return $this->subscriptionDetails->getSubInterval();
    }

    public function getIntervalCount()
    {
        if (!$this->subscriptionDetails)
        {
            throw new InvalidSubscriptionProduct("Subscription details not found.");
        }

        return $this->subscriptionDetails->getSubIntervalCount();
    }

    public function getInitialFeeAmount($qty = 1, $rate = null, $currencyCode = null)
    {
        $baseInitialFee = $this->getBaseInitialFeeAmount() * $qty;

        if ($baseInitialFee <= 0)
            return 0;

        if ($rate == 1)
            return $baseInitialFee;

        if (!$rate)
        {
            $store = $this->storeManager->getStore();
            $baseCurrencyCode = $store->getBaseCurrency()->getCode();

            if ($currencyCode)
            {
                $currentCurrencyCode = $currencyCode;
            }
            else
            {
                $currentCurrencyCode = $store->getCurrentCurrency()->getCode();
            }

            if (!$baseCurrencyCode || !$currentCurrencyCode)
                return $baseInitialFee;

            if ($baseCurrencyCode == $currentCurrencyCode)
                return $baseInitialFee;

            $baseCurrency = $this->currencyFactory->create()->load($baseCurrencyCode);
            $rate = $baseCurrency->getRate($currentCurrencyCode);

            if (!$rate) {
                return $baseInitialFee;
            }
        }

        return round(floatval($baseInitialFee * $rate), 2);
    }
}
