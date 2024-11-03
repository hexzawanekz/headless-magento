<?php
namespace StripeIntegration\Payments\Plugin\Validations;

use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Model\Quote;
use Magento\Payment\Model\Checks\ZeroTotal;

class ZeroTotalCheck
{
    private $config;
    private $paymentMethodHelper;
    private $quoteHelper;

    public function __construct(
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Helper\PaymentMethod $paymentMethodHelper,
        \StripeIntegration\Payments\Helper\Quote $quoteHelper
    )
    {
        $this->config = $config;
        $this->paymentMethodHelper = $paymentMethodHelper;
        $this->quoteHelper = $quoteHelper;
    }

    /**
     * After plugin for isApplicable method
     *
     * @param ZeroTotal $subject
     * @param bool $result
     * @param MethodInterface $paymentMethod
     * @param Quote $quote
     * @return bool
     */
    public function afterIsApplicable(ZeroTotal $subject, $result, MethodInterface $paymentMethod, Quote $quote)
    {
        if ($result)
        {
            // If its already available, there is no need to check if this is a special subscriptions case
            return $result;
        }

        if (!$this->paymentMethodHelper->supportsSubscriptions($paymentMethod->getCode()))
        {
            return $result;
        }

        if (!$this->config->isSubscriptionsEnabled())
        {
            return $result;
        }

        if (!$this->quoteHelper->hasSubscriptions($quote))
        {
            return $result;
        }

        // The following 2 cases may result in a zero total cart, and are applicable
        $hasNonBillableSubscriptionItems = !empty($this->quoteHelper->getNonBillableSubscriptionItems($quote->getAllItems()));
        $hasFullyDiscountedSubscriptions = $this->quoteHelper->hasFullyDiscountedSubscriptions($quote);

        return ($hasNonBillableSubscriptionItems || $hasFullyDiscountedSubscriptions);
    }
}
