<?php

namespace StripeIntegration\Payments\Gateway\Config\RedirectFlow;

use Magento\Payment\Gateway\Config\ValueHandlerInterface;

class CanOrderValueHandler implements ValueHandlerInterface
{
    public function handle(array $subject, $storeId = null)
    {
        return true;
    }
}
