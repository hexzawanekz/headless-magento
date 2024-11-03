<?php

namespace StripeIntegration\Payments\Plugin\Quote;

use Magento\Quote\Model\Quote\Item\ToOrderItem;
use Magento\Sales\Api\Data\OrderItemInterface;

class QuoteItemToOrderItem
{
    public function afterConvert(
        ToOrderItem $subject,
        OrderItemInterface $orderItem,
        $item
    ) {
        $orderItem->setInitialFee($item->getInitialFee());
        $orderItem->setBaseInitialFee($item->getBaseInitialFee());
        $orderItem->setInitialFeeTax($item->getInitialFeeTax());
        $orderItem->setBaseInitialFeeTax($item->getBaseInitialFeeTax());

        return $orderItem;
    }
}