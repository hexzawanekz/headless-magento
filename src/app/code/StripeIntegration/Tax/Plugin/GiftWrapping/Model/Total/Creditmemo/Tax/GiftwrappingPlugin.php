<?php

namespace StripeIntegration\Tax\Plugin\GiftWrapping\Model\Total\Creditmemo\Tax;

use StripeIntegration\Tax\Helper\GiftOptions;
use StripeIntegration\Tax\Model\StripeTransactionReversal\Request\LineItems;
use Magento\GiftWrapping\Model\Total\Creditmemo\Tax\Giftwrapping;
use \Magento\Sales\Model\Order\Creditmemo;

class GiftwrappingPlugin
{
    private $lineItems;
    private $giftOptionsHelper;

    public function __construct(
        LineItems $lineItems,
        GiftOptions $giftOptionsHelper
    )
    {
        $this->lineItems = $lineItems;
        $this->giftOptionsHelper = $giftOptionsHelper;
    }

    public function beforeCollect(
        Giftwrapping $subject,
        Creditmemo $creditmemo
    )
    {
        $order = $creditmemo->getOrder();
        if ($this->giftOptionsHelper->salesObjectHasGiftOptions($order) &&
            $order->getGwTaxAmountInvoiced() != $order->getGwTaxAmountRefunded()
        ) {
            $this->lineItems->setIncludeOrderGW(true);
        }

        if ($this->giftOptionsHelper->salesObjectHasPrintedCard($order) &&
            $order->getGwCardTaxInvoiced() != $order->getGwCardTaxRefunded()
        ) {
            $this->lineItems->setIncludeOrderPrintedCard(true);
        }
    }
}