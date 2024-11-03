<?php

declare(strict_types=1);

namespace StripeIntegration\Payments\Helper;

class Token
{
    public function isConfirmationToken($token)
    {
        if (!is_string($token))
            return false;

        return substr($token, 0, 7) == "ctoken_";
    }

    public function isPaymentMethodToken($token)
    {
        if (!is_string($token))
            return false;

        return substr($token, 0, 3) == "pm_";
    }

    public function isPaymentIntentToken($token)
    {
        if (!is_string($token))
            return false;

        return substr($token, 0, 3) == "pi_";
    }

    public function isSetupIntentToken($token)
    {
        if (!is_string($token))
            return false;

        return substr($token, 0, 5) == "seti_";
    }

    public function isSubscriptionToken($token)
    {
        if (!is_string($token))
            return false;

        return substr($token, 0, 4) == "sub_";
    }

    public function getSetupIntentIdFromClientSecret($clientSecret)
    {
        if (empty($clientSecret))
            return null;

        $parts = explode('_', $clientSecret);

        if (count($parts) < 2)
            return null;

        return implode('_', [$parts[0], $parts[1]]);
    }

    // Removes decorative strings that Magento adds to the transaction ID
    public function cleanToken($token)
    {
        if (empty($token))
            return null;

        return preg_replace('/-.*$/', '', $token);
    }
}