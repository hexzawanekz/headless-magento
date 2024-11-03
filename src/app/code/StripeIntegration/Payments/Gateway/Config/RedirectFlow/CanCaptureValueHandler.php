<?php

namespace StripeIntegration\Payments\Gateway\Config\RedirectFlow;

use Magento\Payment\Gateway\Config\ValueHandlerInterface;

class CanCaptureValueHandler implements ValueHandlerInterface
{
    private $config;
    private $checkoutSession;

    public function __construct(
        \StripeIntegration\Payments\Model\Config $config
    )
    {
        $this->config = $config;
    }

    public function handle(array $subject, $storeId = null)
    {
        $payment = $subject['payment']->getPayment();

        $checkoutSessionId = $payment->getAdditionalInformation("checkout_session_id");
        if (!$checkoutSessionId)
            return false;

        try {
            if (isset($this->checkoutSession) && $this->checkoutSession->id == $checkoutSessionId)
            {
                /** @var \Stripe\Checkout\Session $checkoutSession */
                $checkoutSession = $this->checkoutSession;
            }
            else
            {
                /** @var \Stripe\Checkout\Session $checkoutSession */
                $checkoutSession = $this->checkoutSession = $this->config->getStripeClient()->checkout->sessions->retrieve($checkoutSessionId, [
                    'expand' => [
                        'payment_intent',
                        'payment_intent.payment_method',
                        'setup_intent',
                        'setup_intent.payment_method',
                    ]
                ]);
            }

            if (empty($checkoutSession->payment_intent->capture_method))
            {
                if ($checkoutSession->status == 'complete' && $checkoutSession->mode == 'setup')
                {
                    // Order only mode
                    if (!empty($checkoutSession->setup_intent->payment_method->customer))
                    {
                        return true;
                    }
                }

                return false;
            }

            if ($checkoutSession->payment_intent->capture_method != "manual")
                return false;

            if ($checkoutSession->payment_intent->status == "requires_capture")
                return true;

            if ($checkoutSession->payment_intent->amount_received < $checkoutSession->payment_intent->amount)
            {
                if (!empty($checkoutSession->payment_intent->payment_method->customer))
                {
                    // Multi-invoice in Authorize Only mode
                    return true;
                }
            }

            return false;
        }
        catch (\Exception $e)
        {
            return false;
        }
    }
}
