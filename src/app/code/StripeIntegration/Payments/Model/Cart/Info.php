<?php

declare(strict_types=1);

namespace StripeIntegration\Payments\Model\Cart;

use StripeIntegration\Payments\Exception\Exception;

class Info
{
    private $checkoutFlow;
    private $subscriptionProductFactory;

    // State
    private $hasSubscriptions = false;
    private $hasTrialSubscriptions = false;
    private $hasFutureStartDateSubscriptions = false;
    private $hasRegularProducts = false;
    private $isZeroTotal = true;

    public function __construct(
        \StripeIntegration\Payments\Model\Checkout\Flow $checkoutFlow,
        \StripeIntegration\Payments\Model\SubscriptionProductFactory $subscriptionProductFactory
    )
    {
        $this->checkoutFlow = $checkoutFlow;
        $this->subscriptionProductFactory = $subscriptionProductFactory;
    }

    public function initFromQuote($quote)
    {
        foreach ($quote->getAllItems() as $item)
        {
            $subscriptionProductModel = $this->subscriptionProductFactory->create()->fromQuoteItem($item);
            if ($subscriptionProductModel->isSubscriptionProduct())
            {
                $this->hasSubscriptions = true;

                if ($subscriptionProductModel->hasTrialPeriod())
                    $this->hasTrialSubscriptions = true;

                if ($subscriptionProductModel->startsOnStartDate())
                {
                    $this->hasFutureStartDateSubscriptions = true;
                    $this->checkoutFlow->isFutureSubscriptionSetup = true;
                }

                if (!$subscriptionProductModel->hasZeroInitialOrderPrice())
                    $this->isZeroTotal = false;
            }
            else
            {
                $this->hasRegularProducts = true;

                if ($item->getRowTotal() > 0)
                    $this->isZeroTotal = false;
            }
        }
    }

    public function hasSubscriptions()
    {
        if (!$this->checkoutFlow->isNewOrderBeingPlaced)
            throw new Exception("Do not call this method outside order placements.");

        return $this->hasSubscriptions;
    }

    public function hasTrialSubscriptions()
    {
        if (!$this->checkoutFlow->isNewOrderBeingPlaced)
            throw new Exception("Do not call this method outside order placements.");

        return $this->hasTrialSubscriptions;
    }

    public function hasFutureStartDateSubscriptions()
    {
        if (!$this->checkoutFlow->isNewOrderBeingPlaced)
            throw new Exception("Do not call this method outside order placements.");

        return $this->hasFutureStartDateSubscriptions;
    }

    public function hasRegularProducts()
    {
        if (!$this->checkoutFlow->isNewOrderBeingPlaced)
            throw new Exception("Do not call this method outside order placements.");

        return $this->hasRegularProducts;
    }

    public function isZeroTotal()
    {
        if (!$this->checkoutFlow->isNewOrderBeingPlaced)
            throw new Exception("Do not call this method outside order placements.");

        return $this->isZeroTotal;
    }
}