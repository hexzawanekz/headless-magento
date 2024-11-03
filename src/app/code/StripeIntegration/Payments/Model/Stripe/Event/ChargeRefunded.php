<?php

namespace StripeIntegration\Payments\Model\Stripe\Event;

use StripeIntegration\Payments\Model\Stripe\StripeObjectTrait;

class ChargeRefunded
{
    use StripeObjectTrait;

    private $creditmemoHelper;
    private $webhooksHelper;
    private $creditmemoFactory;
    private $creditmemoService;
    private $orderHelper;
    private $convert;
    private $invoiceHelper;
    private $helper;

    public function __construct(
        \Magento\Sales\Model\Order\CreditmemoFactory $creditmemoFactory,
        \Magento\Sales\Model\Service\CreditmemoService $creditmemoService,
        \StripeIntegration\Payments\Model\Stripe\Service\StripeObjectServicePool $stripeObjectServicePool,
        \StripeIntegration\Payments\Helper\Order $orderHelper,
        \StripeIntegration\Payments\Helper\Convert $convert,
        \StripeIntegration\Payments\Helper\Invoice $invoiceHelper,
        \StripeIntegration\Payments\Helper\Webhooks $webhooksHelper,
        \StripeIntegration\Payments\Helper\Creditmemo $creditmemoHelper,
        \StripeIntegration\Payments\Helper\Generic $helper
    )
    {
        $stripeObjectService = $stripeObjectServicePool->getStripeObjectService('events');
        $this->setData($stripeObjectService);

        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoService = $creditmemoService;
        $this->orderHelper = $orderHelper;
        $this->convert = $convert;
        $this->invoiceHelper = $invoiceHelper;
        $this->creditmemoHelper = $creditmemoHelper;
        $this->webhooksHelper = $webhooksHelper;
        $this->helper = $helper;
    }

    public function process($arrEvent, $object)
    {
        if ($this->webhooksHelper->wasRefundedFromAdmin($object))
            return;

        $order = $this->webhooksHelper->loadOrderFromEvent($arrEvent);

        // Get the refund amount and currency
        $currentRefund = $this->getCurrentRefundFrom($object);
        $currency = $currentRefund['currency'];
        $currencyPrecision = $this->convert->getCurrencyPrecision($currency);
        $refundAmount = $this->convert->stripeAmountToMagentoAmount($currentRefund['amount'], $currency);

        // Record a refund transaction
        if (!empty($object['payment_intent']))
        {
            $transactionType = \Magento\Sales\Model\Order\Payment\Transaction::TYPE_REFUND;
            $paymentIntentId = $object['payment_intent'];
            $transaction = $this->helper->addTransaction($order, $paymentIntentId, $transactionType, $paymentIntentId);
            $transaction->setAdditionalInformation("amount", $refundAmount);
            $transaction->setAdditionalInformation("currency", $object['currency']);
            $transaction->save();
        }

        if ($order->getState() == "holded" && $order->canUnhold())
            $order->unhold();

        // If the Stripe currency does not match the order currency, do not create a credit memo
        if (strtolower($currentRefund['currency']) != strtolower($order->getOrderCurrencyCode()))
        {
            $comment = __("A refund of %1 was issued via Stripe, but the currency is different than the order currency.", $this->helper->addCurrencySymbol($refundAmount, $currency));
            $this->orderHelper->addOrderComment($comment, $order);
            $this->orderHelper->saveOrder($order);
            return false;
        }

        // If the Stripe amount does not match the order amount, do not create a credit memo
        $orderTotal = round(floatval($order->getGrandTotal()), $currencyPrecision);
        $refundTotal = round($refundAmount, $currencyPrecision);
        if ($orderTotal != $refundTotal)
        {
            $comment = __("A refund of %1 was issued via Stripe, but the amount is different than the order amount.", $this->helper->addCurrencySymbol($refundAmount, $currency));
            $this->orderHelper->addOrderComment($comment, $order);
            $this->orderHelper->saveOrder($order);
            return false;
        }

        // If an authorization is partially captured, we expect a payment_intent.succeeded webhook to arrive for the partial capture.
        $isPartialCapture = !$order->canCreditmemo() && $order->canInvoice() && $order->canCancel() && ($refundTotal < $orderTotal);
        if ($isPartialCapture)
        {
            return false;
        }

        // If the refund amount is larger than the order amount, this indicates a problem that should be reported to Stripe's customer support
        if ($refundTotal > $orderTotal)
        {
            $comment = __("A refund of %1 was issued via Stripe, but the amount is bigger than the order amount.", $this->helper->addCurrencySymbol($refundAmount, $currency));
            $this->orderHelper->addOrderComment($comment, $order);
            $this->orderHelper->saveOrder($order);
            return false;
        }
        else if ($refundTotal < $orderTotal)
        {
            $comment = __("A refund of %1 was issued via Stripe, but the amount is smaller than the order amount.", $this->helper->addCurrencySymbol($refundAmount, $currency));
            $this->orderHelper->addOrderComment($comment, $order);
            $this->orderHelper->saveOrder($order);
            return false;
        }

        // If the order has at least one credit memo, do not create another one
        if ($order->hasCreditmemos())
        {
            $comment = __("A refund of %1 was issued via Stripe, but the order already has a credit memo.", $this->helper->addCurrencySymbol($refundAmount, $currency));
            $this->orderHelper->addOrderComment($comment, $order);
            $this->orderHelper->saveOrder($order);
            return false;
        }

        // If the order has multiple invoices, do not create a credit memo
        if ($order->hasInvoices() && count($order->getInvoiceCollection()) > 1)
        {
            $comment = __("A refund of %1 was issued via Stripe, but the order has multiple invoices. Please manually refund the correct invoice offline.", $this->helper->addCurrencySymbol($refundAmount, $currency));
            $this->orderHelper->addOrderComment($comment, $order);
            $this->orderHelper->saveOrder($order);
            return false;
        }

        // If the order has a single invoice which is open...
        $invoice = $order->getInvoiceCollection()->getFirstItem();
        if ($invoice && $invoice->getState() == \Magento\Sales\Model\Order\Invoice::STATE_OPEN)
        {
            $invoiceTotal = round(floatval($invoice->getGrandTotal()), $currencyPrecision);
            if ($invoiceTotal == $refundTotal)
            {
                // If the invoice total matches the refund total, cancel the invoice and the order
                $comment = __("The payment of %1 was canceled via Stripe.", $this->helper->addCurrencySymbol($refundAmount, $currency));
                $invoice->cancel();
                $this->invoiceHelper->saveInvoice($invoice);
                $order->cancel();
                $order->addStatusToHistory($order->getStatus(), $comment);
                $this->orderHelper->saveOrder($order);
                return true;
            }
            else
            {
                $comment = __("A refund of %1 was issued via Stripe, but the invoice amount is different than the refund amount.", $this->helper->addCurrencySymbol($refundAmount, $currency));
                $this->orderHelper->addOrderComment($comment, $order);
                $this->orderHelper->saveOrder($order);
                return false;
            }
        }

        // If the order has a single invoice which is canceled
        if ($invoice && $invoice->getState() == \Magento\Sales\Model\Order\Invoice::STATE_CANCELED)
        {
            $comment = __("A refund of %1 was issued via Stripe, but the invoice is already canceled.", $this->helper->addCurrencySymbol($refundAmount, $currency));
            $this->orderHelper->addOrderComment($comment, $order);
            $this->orderHelper->saveOrder($order);
            return false;
        }

        // A full refund has been issued ($refundTotal == $orderTotal)
        if ($order->canCancel())
        {
            $comment = __("The payment of %1 was canceled via Stripe.", $this->helper->addCurrencySymbol($refundAmount, $currency));
            $order->cancel();
            $order->addStatusToHistory($order->getStatus(), $comment);
            $this->orderHelper->saveOrder($order);
            return true;
        }
        else if ($order->canCreditmemo())
        {
            if ($invoice)
            {
                if ($invoice->getState() == \Magento\Sales\Model\Order\Invoice::STATE_PAID)
                {
                    // Refund the invoice offline
                    $creditmemo = $this->createOfflineCreditmemoForInvoice($invoice, $order);
                    $comment = __("We refunded %1 through Stripe.", $this->helper->addCurrencySymbol($refundAmount, $currency));
                    $order->addStatusToHistory($order->getStatus(), $comment);
                    $this->creditmemoHelper->saveCreditmemo($creditmemo);
                    $this->orderHelper->saveOrder($order);
                    return true;
                }
                else
                {
                    // This should never hit due to prior invoice status checks
                    $comment = __("A refund of %1 was issued via Stripe, but the invoice is not paid.", $this->helper->addCurrencySymbol($refundAmount, $currency));
                    $this->orderHelper->addOrderComment($comment, $order);
                    $this->orderHelper->saveOrder($order);
                    return false;
                }
            }
            else
            {
                $comment = __("A refund of %1 was issued via Stripe, but the order has not yet been invoiced.", $this->helper->addCurrencySymbol($refundAmount, $currency));
                $this->orderHelper->addOrderComment($comment, $order);
                $this->orderHelper->saveOrder($order);
                return false;
            }
        }
        else if (!$order->canCreditmemo())
        {
            // Unknown case which should never hit
            $comment = __("A refund of %1 was issued via Stripe, but a Credit Memo could not be created.", $this->helper->addCurrencySymbol($refundAmount, $currency));
            $this->orderHelper->addOrderComment($comment, $order);
            $this->orderHelper->saveOrder($order);
            return false;
        }

        return true;
    }

    private function getCurrentRefundFrom($webhookData)
    {
        $lastRefundDate = 0;
        $currentRefund = null;

        foreach ($webhookData['refunds']['data'] as $refund)
        {
            // There might be multiple refunds, and we are looking for the most recent one
            if ($refund['created'] > $lastRefundDate)
            {
                $lastRefundDate = $refund['created'];
                $currentRefund = $refund;
            }
        }

        return $currentRefund;
    }

    private function createOfflineCreditmemoForInvoice($invoice, $order)
    {
        // Prepare credit memo data
        $creditmemo = $this->creditmemoFactory->createByOrder($order);
        $creditmemo->setInvoice($invoice);

        // Set refund to offline mode
        $creditmemo->setRefundRequested(true);
        $creditmemo->setOfflineRequested(true);

        // Refund to the customer and save credit memo
        $this->creditmemoHelper->refundCreditmemo($creditmemo);

        return $creditmemo;
    }
}