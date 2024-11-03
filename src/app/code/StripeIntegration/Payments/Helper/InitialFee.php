<?php

namespace StripeIntegration\Payments\Helper;

class InitialFee
{
    private $paymentsHelper;
    private $checkoutSessionHelper;
    private $subscriptionProductFactory;
    private $paymentMethodHelper;

    public function __construct(
        \StripeIntegration\Payments\Model\SubscriptionProductFactory $subscriptionProductFactory,
        \StripeIntegration\Payments\Helper\Generic $paymentsHelper,
        \StripeIntegration\Payments\Helper\CheckoutSession $checkoutSessionHelper,
        \StripeIntegration\Payments\Helper\PaymentMethod $paymentMethodHelper
    ) {
        $this->subscriptionProductFactory = $subscriptionProductFactory;
        $this->paymentsHelper = $paymentsHelper;
        $this->checkoutSessionHelper = $checkoutSessionHelper;
        $this->paymentMethodHelper = $paymentMethodHelper;
    }

    public function getTotalInitialFeeForCreditmemo($creditmemo, $orderRate = true)
    {
        $payment = $creditmemo->getOrder()->getPayment();

        if (empty($payment))
            return 0;

        if (!$this->paymentMethodHelper->supportsSubscriptions($payment->getMethod()))
            return 0;

        if ($payment->getAdditionalInformation("is_recurring_subscription") || $payment->getAdditionalInformation("remove_initial_fee"))
            return 0;

        $items = $creditmemo->getAllItems();

        if ($orderRate)
            $rate = $creditmemo->getBaseToOrderRate();
        else
            $rate = 1;

        return $this->getInitialFeeForItems($items, $rate);
    }

    public function getTotalInitialFeeTaxForCreditmemo($creditmemo)
    {
        $payment = $creditmemo->getOrder()->getPayment();

        if (empty($payment))
            return 0;

        if (!$this->paymentMethodHelper->supportsSubscriptions($payment->getMethod()))
            return 0;

        if ($payment->getAdditionalInformation("is_recurring_subscription") || $payment->getAdditionalInformation("remove_initial_fee"))
            return 0;

        return $this->getInitialFeeTaxForCreditmemoItems($creditmemo);
    }

    public function getTotalInitialFeeForInvoice($invoice, $invoiceRate = true)
    {
        $payment = $invoice->getOrder()->getPayment();

        if (empty($payment))
            return 0;

        if (!$this->paymentMethodHelper->supportsSubscriptions($payment->getMethod()))
            return 0;

        if ($payment->getAdditionalInformation("is_recurring_subscription") || $payment->getAdditionalInformation("remove_initial_fee"))
            return 0;

        $items = $invoice->getAllItems();

        if ($invoiceRate)
            $rate = $invoice->getBaseToOrderRate();
        else
            $rate = 1;

        return $this->getInitialFeeForItems($items, $rate);
    }

    public function getTotalInitialFeeForOrder($filteredOrderItems, $order): array
    {
        if ($order->getIsRecurringOrder() || $order->getRemoveInitialFee()) {
            return [
                "initial_fee" => 0,
                "base_initial_fee" => 0
            ];
        }

        if ($this->checkoutSessionHelper->isSubscriptionUpdate()) {
            return [
                "initial_fee" => 0,
                "base_initial_fee" => 0
            ];
        }

        if ($this->checkoutSessionHelper->isSubscriptionReactivate()) {
            return [
                "initial_fee" => 0,
                "base_initial_fee" => 0
            ];
        }

        $baseTotal = $total = 0;

        foreach ($filteredOrderItems as $orderItem)
        {
            if ($orderItem->getInitialFee() > 0)
            {
                // From 3.4.0 onwards, the initial fee is saved on the order item
                $total += $orderItem->getInitialFee();
                $baseTotal += $orderItem->getBaseInitialFee();
            }
        }

        return [
            "initial_fee" => $total,
            "base_initial_fee" => $baseTotal
        ];
    }

    public function getTotalInitialFeeFor($items, $quote, $quoteRate = 1)
    {
        if ($quote->getIsRecurringOrder() || $quote->getRemoveInitialFee())
            return 0;

        return $this->getInitialFeeForItems($items, $quoteRate);
    }

    public function getInitialFeeForItems($items, $rate)
    {
        if ($this->checkoutSessionHelper->isSubscriptionUpdate())
            return 0;

        if ($this->checkoutSessionHelper->isSubscriptionReactivate())
            return 0;

        $total = 0;

        foreach ($items as $item)
        {
            $productId = $item->getProductId();
            $qty = $this->getItemQty($item, $productId);
            $total += $this->getInitialFeeForProductId($productId, $rate, $qty);
        }
        return $total;
    }

    public function getInitialFeeTaxForCreditmemoItems($creditmemo)
    {
        if ($this->checkoutSessionHelper->isSubscriptionUpdate())
            return 0;

        if ($this->checkoutSessionHelper->isSubscriptionReactivate())
            return 0;

        $totalInitialFeeTax = 0;
        $totalInitialFeeBaseTax = 0;

        foreach ($creditmemo->getAllItems() as $item)
        {
            $productId = $item->getProductId();
            $subscriptionProductModel = $this->subscriptionProductFactory->create()->fromProductId($productId);

            if (!$subscriptionProductModel->isSubscriptionProduct()) {
                continue;
            }
            $orderItem = $item->getOrderItem();
            $creditMemoItemQty = $this->getItemQty($item, $productId);
            $orderItemQty = $orderItem->getQtyOrdered();
            $orderInitialFeeTax = $orderItem->getInitialFeeTax();
            $orderBaseInitialFeeTax = $orderItem->getBaseInitialFeeTax();

            // Get the value of the tax for one item in the order and multiply by the number of credit memo items
            $totalInitialFeeTax += round ($orderInitialFeeTax / $orderItemQty * $creditMemoItemQty, 2);
            $totalInitialFeeBaseTax += round ($orderBaseInitialFeeTax / $orderItemQty * $creditMemoItemQty, 2);
        }

        return ['tax' => $totalInitialFeeTax, 'base_tax' => $totalInitialFeeBaseTax];
    }

    private function getInitialFeeForProductId($productId, $rate, $qty)
    {
        $subscriptionProductModel = $this->subscriptionProductFactory->create()->fromProductId($productId);

        if (!$subscriptionProductModel->isSubscriptionProduct())
            return 0;

        return $subscriptionProductModel->getInitialFeeAmount($qty, $rate);
    }

    public function getAdditionalOptionsForQuoteItem($quoteItem, $currencyCode = null)
    {
        if (!empty($quoteItem->getQtyOptions()))
        {
            return $this->getAdditionalOptionsForChildrenOf($quoteItem, $currencyCode);
        }
        else
        {
            return $this->getAdditionalOptionsForProductId($quoteItem->getProductId(), $quoteItem, $currencyCode);
        }
    }

    private function getAdditionalOptionsForChildrenOf($item, $currencyCode)
    {
        $additionalOptions = [];

        foreach ($item->getQtyOptions() as $productId => $option)
        {
            $additionalOptions = array_merge($additionalOptions, $this->getAdditionalOptionsForProductId($productId, $item, $currencyCode));
        }

        return $additionalOptions;
    }

    private function getAdditionalOptionsForProductId($productId, $quoteItem, $currencyCode)
    {
        $qty = $this->getItemQty($quoteItem, $productId);

        $subscriptionProductModel = $this->subscriptionProductFactory->create()->fromProductId($productId);
        if (!$subscriptionProductModel->isSubscriptionProduct())
            return [];

        $additionalOptions = [
            [
                'label' => 'Repeats Every',
                'value' => $subscriptionProductModel->getFormattedInterval()
            ]
        ];

        $initialFee = $subscriptionProductModel->getInitialFeeAmount($qty, null, $currencyCode);

        if ($initialFee > 0)
        {
            $additionalOptions[] = [
                'label' => 'Initial Fee',
                'value' => $this->paymentsHelper->addCurrencySymbol($initialFee, $currencyCode)
            ];
        }

        $trialDays = $subscriptionProductModel->getTrialDays();

        if ($trialDays > 0)
        {
            $additionalOptions[] = [
                'label' => 'Trial Period',
                'value' => $trialDays . " days"
            ];
        }

        return $additionalOptions;
    }

    public function getItemQty($item, $productId)
    {
        $qty = max(/* quote */ $item->getQty(), /* order */ $item->getQtyOrdered());

        if ($item->getParentItem())
        {
            // The child product was passed
            $parentProductType = $item->getParentItem()->getProductType();
            if (in_array($parentProductType, ["configurable", "bundle"]))
            {
                $parentQty = max(/* quote */ $item->getParentItem()->getQty(), /* order */ $item->getParentItem()->getQtyOrdered());
                if (is_numeric($parentQty))
                    $qty *= $parentQty;
            }
        }
        else if (!empty($item->getQtyOptions()))
        {
            // The parent product was passed
            foreach ($item->getQtyOptions() as $qtyProductId => $option)
            {
                if ($qtyProductId == $productId)
                {
                    $qty *= $option->getValue();
                    break;
                }
            }
        }

        return $qty;
    }
}
