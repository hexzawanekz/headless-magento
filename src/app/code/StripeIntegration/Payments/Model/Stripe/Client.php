<?php

declare(strict_types=1);

namespace StripeIntegration\Payments\Model\Stripe;

use Magento\Framework\Exception\LocalizedException;

class Client
{
    private $config;
    private $cache;
    private $errorHelper;

    public function __construct(
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Helper\Error $errorHelper,
        \Magento\Framework\App\CacheInterface $cache
    )
    {
        $this->config = $config;
        $this->errorHelper = $errorHelper;
        $this->cache = $cache;
    }

    public function getStripeClient()
    {
        return $this->config->getStripeClient();
    }

    public function adminConfirmPaymentIntent($paymentIntentId, $confirmParams)
    {
        $key = "admin_captured_" . $paymentIntentId;

        try
        {
            $this->cache->save($value = "1", $key, ["stripe_payments"], $lifetime = 60 * 60);
            return $this->getStripeClient()->paymentIntents->confirm($paymentIntentId, $confirmParams);
        }
        catch (\Stripe\Exception\InvalidRequestException $e)
        {
            if (!$this->errorHelper->isMOTOError($e->getError()))
            {
                $this->cache->remove($key);
                throw $e;
            }

            $this->cache->save($value = "1", $key = "no_moto_gate", ["stripe_payments"], $lifetime = 6 * 60 * 60);
            unset($confirmParams['payment_method_options']['card']['moto']);
            $confirmParams['off_session'] = true;
            $result = $this->config->getStripeClient()->paymentIntents->confirm($paymentIntentId, $confirmParams);

            if ($result->status == "requires_action")
                throw new LocalizedException(__("This payment method cannot be used because it requires a customer authentication. To avoid authentication in the admin area, please contact Stripe support to request access to the MOTO gate for your Stripe account."));

            return $result;
        }
    }
}