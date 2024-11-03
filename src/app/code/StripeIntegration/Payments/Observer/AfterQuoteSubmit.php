<?php

namespace StripeIntegration\Payments\Observer;

use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Framework\Event\Observer;

// sales_model_service_quote_submit_success
// sales_model_service_quote_submit_failure
class AfterQuoteSubmit extends AbstractDataAssignObserver
{
    private $checkoutFlow;
    private $quoteHelper;

    public function __construct(
        \StripeIntegration\Payments\Model\Checkout\Flow $checkoutFlow,
        \StripeIntegration\Payments\Helper\Quote $quoteHelper
    )
    {
        $this->checkoutFlow = $checkoutFlow;
        $this->quoteHelper = $quoteHelper;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $quote = $observer->getEvent()->getQuote();

        if ($this->checkoutFlow->isQuoteCorrupted)
        {
            $this->restoreTotals($quote);
        }
    }

    private function restoreTotals($quote)
    {
        foreach ($quote->getAllItems() as $item)
        {
            if ($item->getStripeOriginalSubscriptionPrice())
            {
                $item->setCustomPrice($item->getStripeOriginalSubscriptionPrice());
                $item->setOriginalCustomPrice($item->getStripeOriginalSubscriptionPrice());
                $item->getProduct()->setIsSuperMode(true);
                $item->setStripeOriginalSubscriptionPrice(null);
                $item->setStripeBaseOriginalSubscriptionPrice(null);
                $this->checkoutFlow->isQuoteCorrupted = false;
            }
        }
        $this->checkoutFlow->disableZeroInitialPrices();
        $this->quoteHelper->reCollectTotals($quote);
    }
}