<?php

namespace StripeIntegration\Payments\Gateway\Command\RedirectFlow;

use Magento\Payment\Gateway\CommandInterface;

class RefundCommand implements CommandInterface
{
    private $refundsHelper;
    private $helper;

    public function __construct(
        \StripeIntegration\Payments\Helper\Refunds $refundsHelper,
        \StripeIntegration\Payments\Helper\Generic $helper
    ) {
        $this->refundsHelper = $refundsHelper;
        $this->helper = $helper;
    }

    public function execute(array $commandSubject): void
    {
        $payment = $commandSubject['payment']->getPayment();
        $amount = $commandSubject['amount'];

        try
        {
            $this->refundsHelper->refund($payment, $amount);
        }
        catch (\Exception $e)
        {
            $this->helper->throwError($e->getMessage());
        }
    }
}
