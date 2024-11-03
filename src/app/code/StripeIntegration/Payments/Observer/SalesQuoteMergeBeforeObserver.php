<?php

namespace StripeIntegration\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

// sales_quote_merge_before
class SalesQuoteMergeBeforeObserver implements ObserverInterface
{
    private $config;
    private $quoteHelper;

    public function __construct(
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Helper\Quote $quoteHelper
    )
    {
        $this->config = $config;
        $this->quoteHelper = $quoteHelper;
    }

    public function execute(Observer $observer)
    {
        if (!$this->config->isSubscriptionsEnabled())
            return;

        // Retrieve the quotes from the observer
        $guestQuote = $observer->getEvent()->getSource();
        $destinationQuote = $observer->getEvent()->getQuote();

        if (!$guestQuote || !$destinationQuote)
            return;

        $guestQuoteHasSubscriptions = $this->quoteHelper->hasSubscriptions($guestQuote);
        $destinationQuoteHasSubscriptions = $this->quoteHelper->hasSubscriptions($destinationQuote);

        if ($guestQuoteHasSubscriptions && $destinationQuoteHasSubscriptions)
        {
            // Remove subscriptions from the destination quote
            $this->quoteHelper->removeSubscriptions($destinationQuote);
        }
    }
}
