<?php

declare(strict_types=1);

namespace StripeIntegration\Payments\Model\Checkout;

class Flow
{
    public $shouldIncrementCouponUsage = true;
    public $isExpressCheckout = false;
    public $isFutureSubscriptionSetup = false;
    public $isPendingMicrodepositsVerification = false;
    public $creatingOrderFromCharge = null;
    public $isNewOrderBeingPlaced = false;
    public $isRecurringSubscriptionOrderBeingPlaced = false;
    public $isQuoteCorrupted = false;
    private $disableZeroInitialPrices = false;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    )
    {
        $incrementCouponUsageAfterOrderPlaced = $scopeConfig->getValue('stripe_settings/increment_coupon_usage_after_order_placed', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, 0);
        if ($incrementCouponUsageAfterOrderPlaced)
        {
            $this->shouldIncrementCouponUsage = false;
        }
    }

    public function shouldNotBillTrialSubscriptionItems()
    {
        return $this->isNewOrderBeingPlaced && !$this->isRecurringSubscriptionOrderBeingPlaced && !$this->disableZeroInitialPrices;
    }

    public function disableZeroInitialPrices()
    {
        $this->disableZeroInitialPrices = true;
    }

    public function enableZeroInitialPrices()
    {
        $this->disableZeroInitialPrices = false;
    }

    public function shouldIncrementCouponUsage($quote)
    {
        $paymentMethodCode = $quote->getPayment()->getMethod();
        if ($paymentMethodCode != "stripe_payments")
        {
            return true;
        }
        else
        {
            return $this->shouldIncrementCouponUsage;
        }
    }
}