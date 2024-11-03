<?php

declare(strict_types=1);

namespace StripeIntegration\Payments\Plugin\CartRepository;

use StripeIntegration\Payments\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;

class BeforeSave
{
    private $checkoutFlow;

    public function __construct(
        \StripeIntegration\Payments\Model\Checkout\Flow $checkoutFlow
    )
    {
        $this->checkoutFlow = $checkoutFlow;
    }

    public function beforeSave(
        CartRepositoryInterface $subject,
        CartInterface $quote
    ) {
        if ($this->checkoutFlow->isQuoteCorrupted && $quote->getIsActive())
        {
            throw new LocalizedException(__("Cannot save quote because its totals are corrupted."));
        }

        return [$quote];
    }
}