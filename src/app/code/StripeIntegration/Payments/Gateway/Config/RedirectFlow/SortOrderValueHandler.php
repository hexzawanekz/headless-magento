<?php

namespace StripeIntegration\Payments\Gateway\Config\RedirectFlow;

use Magento\Payment\Gateway\Config\ValueHandlerInterface;

class SortOrderValueHandler implements ValueHandlerInterface
{
    private $config;

    public function __construct(
        \StripeIntegration\Payments\Model\Config $config
    )
    {
        $this->config = $config;
    }
    public function handle(array $subject, $storeId = null)
    {
        // This retrieves payment/stripe_payments/sort_order instead of payment/stripe_payments_checkout/sort_order
        $value = $this->config->getConfigData("sort_order");
        return $value;
    }
}
