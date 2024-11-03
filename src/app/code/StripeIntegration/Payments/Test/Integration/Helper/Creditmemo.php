<?php

namespace StripeIntegration\Payments\Test\Integration\Helper;

class Creditmemo
{
    public function getCreditmemoItem($creditmemo, $sku)
    {
        foreach ($creditmemo->getAllItems() as $creditmemoItem)
        {
            if ($creditmemoItem->getSku() == $sku)
                return $creditmemoItem;
        }

        return null;
    }

    public function getTotalForTransaction($transaction, $taxBehaviour)
    {
        $total = 0;
        foreach ($transaction->line_items->data as $lineItem) {
            $total += $lineItem->amount;
            if ($taxBehaviour == 'exclusive') {
                $total += $lineItem->amount_tax;
            }
        }
        if (isset($transaction->shipping_cost)) {
            $total += $transaction->shipping_cost->amount;
            if ($taxBehaviour == 'exclusive') {
                $total += $transaction->shipping_cost->amount_tax;
            }
        }

        return abs($total / 100);
    }
}