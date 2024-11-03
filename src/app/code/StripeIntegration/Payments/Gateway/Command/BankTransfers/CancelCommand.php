<?php

namespace StripeIntegration\Payments\Gateway\Command\BankTransfers;

use Magento\Payment\Gateway\CommandInterface;

class CancelCommand implements CommandInterface
{
    private $refundsHelper;

    public function __construct(
        \StripeIntegration\Payments\Helper\Refunds $refundsHelper
    ) {
        $this->refundsHelper = $refundsHelper;
    }

    public function execute(array $commandSubject): void
    {
        $payment = $commandSubject['payment']->getPayment();
        $this->refundsHelper->refund($payment);
    }
}
