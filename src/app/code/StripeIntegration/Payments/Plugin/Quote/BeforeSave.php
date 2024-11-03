<?php

declare(strict_types=1);

namespace StripeIntegration\Payments\Plugin\Quote;

use StripeIntegration\Payments\Exception\LocalizedException;
use Magento\Quote\Model\Quote;

class BeforeSave
{
    private $checkoutFlow;

    public function __construct(
        \StripeIntegration\Payments\Model\Checkout\Flow $checkoutFlow
    )
    {
        $this->checkoutFlow = $checkoutFlow;
    }

    public function beforeSave(Quote $quote)
    {
        if ($this->checkoutFlow->isQuoteCorrupted && $quote->getIsActive())
        {
            throw new LocalizedException(__("Cannot save quote because its totals are corrupted."));
        }

        return [];
    }
}