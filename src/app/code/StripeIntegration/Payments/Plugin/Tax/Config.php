<?php

namespace StripeIntegration\Payments\Plugin\Tax;

class Config
{
    private $taxCalculation;

    public function __construct(
        \StripeIntegration\Payments\Model\Tax\Calculation $taxCalculation
    )
    {
        $this->taxCalculation = $taxCalculation;
    }

    public function afterGetAlgorithm(
        $subject,
        $result,
        $storeId = null
    ) {
        if (!empty($this->taxCalculation->method)) {
            return $this->taxCalculation->method;
        }

        return $result;
    }
}
