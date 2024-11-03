<?php

namespace StripeIntegration\Payments\Plugin\SalesRule;

use Magento\SalesRule\Model\Coupon\Quote\UpdateCouponUsages;
use Magento\Quote\Api\Data\CartInterface;

class UpdateCouponUsagesPlugin
{
    private $checkoutFlow;

    public function __construct(
        \StripeIntegration\Payments\Model\Checkout\Flow $checkoutFlow
    )
    {
        $this->checkoutFlow = $checkoutFlow;
    }

    /**
     * Around execute plugin
     *
     * @param UpdateCouponUsages $subject
     * @param \Closure $proceed
     * @param CartInterface $quote
     * @param bool $increment
     */
    public function aroundExecute(
        UpdateCouponUsages $subject,
        \Closure $proceed,
        CartInterface $quote,
        bool $increment
    ): void {
        if (!$this->checkoutFlow->shouldIncrementCouponUsage($quote))
        {
            return;
        }
        else
        {
            $proceed($quote, $increment);
        }
    }
}
