<?php
namespace StripeIntegration\Payments\Plugin\Multishipping;

class Helper
{
    private $config;
    private $subscriptionsHelper;

    public function __construct(
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Helper\Subscriptions $subscriptionsHelper
    ) {
        $this->config = $config;
        $this->subscriptionsHelper = $subscriptionsHelper;
    }

    public function aroundIsMultishippingCheckoutAvailable(\Magento\Multishipping\Helper\Data $subject, \Closure $proceed)
    {
        if ($this->config->isSubscriptionsEnabled() && $this->subscriptionsHelper->hasSubscriptions())
            return false;

        return $proceed();
    }
}
