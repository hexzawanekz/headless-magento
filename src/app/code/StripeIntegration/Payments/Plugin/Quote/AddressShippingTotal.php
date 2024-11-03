<?php

namespace StripeIntegration\Payments\Plugin\Quote;

use Magento\Quote\Model\Quote\Address\Total;

class AddressShippingTotal
{
    private $config;
    private $quoteHelper;
    private $checkoutFlow;

    public function __construct(
        \StripeIntegration\Payments\Helper\Quote $quoteHelper,
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Model\Checkout\Flow $checkoutFlow
    )
    {
        $this->quoteHelper = $quoteHelper;
        $this->config = $config;
        $this->checkoutFlow = $checkoutFlow;
    }

    public function aroundCollect(
        \Magento\Quote\Model\Quote\Address\Total\Shipping $subject,
        callable $proceed,
        \Magento\Quote\Model\Quote $quote,
        \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignment,
        \Magento\Quote\Model\Quote\Address\Total $total
    ) {
        if ($this->config->isSubscriptionsEnabled() && $this->checkoutFlow->shouldNotBillTrialSubscriptionItems())
        {
            $items = $shippingAssignment->getItems();
            $nonBillableSubscriptionItems = [];
            $billableItems = [];

            $nonBillableSubscriptionItems = $this->quoteHelper->getNonBillableSubscriptionItems($shippingAssignment->getItems());

            if (!empty($nonBillableSubscriptionItems))
            {
                $billableItems = array_filter($items, function($item) use ($nonBillableSubscriptionItems) {
                    return !in_array($item, $nonBillableSubscriptionItems);
                });

                if (!empty($billableItems))
                {
                    $shippingAssignment->setItems($billableItems);
                    $proceed($quote, $shippingAssignment, $total);
                    $shippingAssignment->setItems($items);
                    $this->checkoutFlow->isQuoteCorrupted = true;
                }
                else
                {
                    $total->setBaseShippingAmount(0);
                    $total->setShippingAmount(0);
                    $this->checkoutFlow->isQuoteCorrupted = true;
                }
            }
            else
            {
                $proceed($quote, $shippingAssignment, $total);
            }

            return $subject;
        }
        else
        {
            $proceed($quote, $shippingAssignment, $total);
            return $subject;
        }
    }
}
