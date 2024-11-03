<?php
namespace StripeIntegration\Payments\Plugin\Quote;

use Magento\Quote\Model\Quote as QuoteEntity;

class QuoteManagement
{
    private $checkoutFlow;
    private $quoteHelper;
    private $config;
    private $cartInfo;
    private $quoteResourceModel;

    public function __construct(
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Model\Checkout\Flow $checkoutFlow,
        \StripeIntegration\Payments\Model\Cart\Info $cartInfo,
        \StripeIntegration\Payments\Helper\Quote $quoteHelper,
        \Magento\Quote\Model\ResourceModel\Quote $quoteResourceModel
    )
    {
        $this->checkoutFlow = $checkoutFlow;
        $this->cartInfo = $cartInfo;
        $this->quoteHelper = $quoteHelper;
        $this->config = $config;
        $this->quoteResourceModel = $quoteResourceModel;
    }

    public function beforeSubmit(
        \Magento\Quote\Model\QuoteManagement $subject,
        QuoteEntity $quote,
        $orderData = []
    )
    {
        $paymentMethodCode = (string)$quote->getPayment()->getMethod();

        if (strstr($paymentMethodCode, 'stripe_') === false)
            return [$quote, $orderData];

        // Avoid order number skipping in the case of payment failures
        $quote->reserveOrderId();

        // We intentionally do not use the quote repository for saving, because that would trigger
        // a shipping rate recollection which will reset the selected shipping method
        $this->quoteResourceModel->save($quote);

        // Build info details about the quote
        $this->cartInfo->initFromQuote($quote);

        // Adjust quote totals to account for trial subscriptions and subscriptions with start dates
        $this->checkoutFlow->isNewOrderBeingPlaced = true;
        $this->setTrialSubscriptionCustomPrice($quote);
        $this->quoteHelper->reCollectTotals($quote);

        return [$quote, $orderData];
    }

    public function afterSubmit(
        \Magento\Quote\Model\QuoteManagement $subject,
        $returnValue,
        QuoteEntity $quote
    )
    {
        $paymentMethodCode = (string)$quote->getPayment()->getMethod();

        if (strstr($paymentMethodCode, 'stripe_') === false)
            return $returnValue;

        $this->checkoutFlow->isNewOrderBeingPlaced = false;

        // Totals are restored inside AfterQuoteSubmit observer, this method is only kept in case the submission fails

        return $returnValue;
    }

    private function setTrialSubscriptionCustomPrice($quote)
    {
        if (!$this->config->isSubscriptionsEnabled())
            return;

        if (!$this->checkoutFlow->shouldNotBillTrialSubscriptionItems())
            return;

        $items = $this->quoteHelper->getNonBillableSubscriptionItems($quote->getAllItems());
        foreach ($items as $item)
        {
            $item->setCustomPrice(0);
            $item->setOriginalCustomPrice(0);
            $item->getProduct()->setIsSuperMode(true);
            $this->checkoutFlow->isQuoteCorrupted = true; // Because the subtotal is wrong

            // Save a reference of the original prices
            if (!$item->getStripeOriginalSubscriptionPrice())
            {
                if ($this->config->priceIncludesTax())
                {
                    $item->setStripeOriginalSubscriptionPrice($item->getPriceInclTax());
                }
                else
                {
                    $item->setStripeOriginalSubscriptionPrice($item->getConvertedPrice());
                }
            }
        }
    }
}
