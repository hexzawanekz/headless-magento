<?php

namespace StripeIntegration\Payments\Gateway\Command\RedirectFlow;

use Magento\Payment\Gateway\CommandInterface;
use Magento\Framework\Exception\LocalizedException;

class CaptureCommand implements CommandInterface
{
    private $config;
    private $api;
    private $checkoutSessionHelper;
    private $helper;

    public function __construct(
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Helper\Api $api,
        \StripeIntegration\Payments\Helper\Stripe\CheckoutSession $checkoutSessionHelper,
        \StripeIntegration\Payments\Helper\Generic $helper
    ) {
        $this->config = $config;
        $this->api = $api;
        $this->checkoutSessionHelper = $checkoutSessionHelper;
        $this->helper = $helper;
    }

    public function execute(array $commandSubject): void
    {
        $payment = $commandSubject['payment']->getPayment();
        $amount = $commandSubject['amount'];

        if ($payment->getAdditionalInformation("payment_action") == "order" &&
            $payment->getAdditionalInformation("customer_stripe_id") &&
            $payment->getAdditionalInformation("token"))
        {
            $this->api->createNewCharge($payment, $amount);
            return;
        }

        $transactionId = $this->checkoutSessionHelper->getLastTransactionId($payment);

        if (!$transactionId)
        {
            throw new LocalizedException(__('Sorry, it is not possible to invoice this order because the payment is still pending.'));
        }

        try
        {
            $this->helper->capture($transactionId, $payment, $amount, $this->config->retryWithSavedCard());
        }
        catch (\Exception $e)
        {
            $this->helper->throwError($e->getMessage());
        }
    }
}
