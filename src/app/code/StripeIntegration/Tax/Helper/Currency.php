<?php

namespace StripeIntegration\Tax\Helper;

class Currency
{
    public const ZERO_DECIMAL_CURRENCIES = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];
    public const THREE_DECIMAL_CURRENCIES = ['BHD', 'JOD', 'KWD', 'OMR', 'TND'];

    public function isZeroDecimalCurrency(string $currency)
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL_CURRENCIES);
    }

    public function isThreeDecimalCurrency(string $currency)
    {
        return in_array(strtoupper($currency), self::THREE_DECIMAL_CURRENCIES);
    }

    public function magentoAmountToStripeAmount($amount, $currency): int
    {
        if (!is_numeric($amount))
            return 0;

        $amount = floatval($amount);

        if ($this->isZeroDecimalCurrency($currency))
            return (int) round($amount);

        if ($this->isThreeDecimalCurrency($currency))
            return (int) round($amount * 100) * 10;

        return (int) round($amount * 100);
    }

    public function stripeAmountToMagentoAmount($amount, string $currency): float
    {
        if (!is_numeric($amount))
            return 0.0;

        $amount = floatval($amount);
        $precision = $this->getCurrencyPrecision($currency);

        return $amount / pow(10, $precision);
    }

    public function getCurrencyPrecision(string $currency): int
    {
        if ($this->isZeroDecimalCurrency($currency))
            return 0;

        if ($this->isThreeDecimalCurrency($currency))
            return 3;

        return 2;
    }

    public function hasDecimals($amount)
    {
        return !(($amount - (int)$amount) == 0);
    }
}