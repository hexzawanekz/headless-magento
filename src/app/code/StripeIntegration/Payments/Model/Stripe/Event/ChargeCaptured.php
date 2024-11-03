<?php

namespace StripeIntegration\Payments\Model\Stripe\Event;

use StripeIntegration\Payments\Model\Stripe\StripeObjectTrait;

class ChargeCaptured
{
    use StripeObjectTrait;

    private $webhooksHelper;
    private $helper;
    private $orderHelper;
    private $convert;

    public function __construct(
        \StripeIntegration\Payments\Model\Stripe\Service\StripeObjectServicePool $stripeObjectServicePool,
        \StripeIntegration\Payments\Helper\Webhooks $webhooksHelper,
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Helper\Order $orderHelper,
        \StripeIntegration\Payments\Helper\Convert $convert
    )
    {
        $stripeObjectService = $stripeObjectServicePool->getStripeObjectService('events');
        $this->setData($stripeObjectService);

        $this->webhooksHelper = $webhooksHelper;
        $this->helper = $helper;
        $this->orderHelper = $orderHelper;
        $this->convert = $convert;
    }

    public function process($arrEvent, $object)
    {
        if ($this->webhooksHelper->wasCapturedFromAdmin($object))
            return;

        if (empty($object['payment_intent']))
            return;

        $order = $this->webhooksHelper->loadOrderFromEvent($arrEvent);

        $paymentIntentId = $object['payment_intent'];
        $chargeAmount = $this->helper->convertStripeAmountToOrderAmount($object['amount_captured'], $object['currency'], $order);
        $transactionType = \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE;
        $transaction = $this->helper->addTransaction($order, $paymentIntentId, $transactionType, $paymentIntentId);
        $transaction->setAdditionalInformation("amount", $chargeAmount);
        $transaction->setAdditionalInformation("currency", $object['currency']);
        $transaction->save();

        $currency = $object['currency'];
        $amountCaptured = (int)$object['amount_captured'];
        $orderTotal = $this->convert->magentoAmountToStripeAmount($order->getGrandTotal(), $currency);
        $magentoAmount = $this->convert->stripeAmountToMagentoAmount($amountCaptured, $currency);
        $humanReadableAmount = $this->helper->addCurrencySymbol($magentoAmount, $currency);

        if ($orderTotal > $amountCaptured)
        {
            $comment = __("Partially captured %1 via Stripe, but it less than the order total. Please invoice the order offline with the correct order items. Transaction ID: %2", $humanReadableAmount, $paymentIntentId);
            $order->addStatusToHistory(false, $comment, $isCustomerNotified = false);
            $this->orderHelper->saveOrder($order);
            return;
        }
        else if ($orderTotal < $amountCaptured)
        {
            $comment = __("Captured %1 via Stripe, but it is more than the order total. No invoice is be created. Transaction ID: %2", $humanReadableAmount, $paymentIntentId);
            $order->addStatusToHistory(false, $comment, $isCustomerNotified = false);
            $this->orderHelper->saveOrder($order);
            return;
        }

        // Get any existing invoices
        $invoices = $order->getInvoiceCollection();

        // If there are multiple invoices, do nothing
        if ($invoices->count() > 1)
        {
            $comment = __("Captured %1 via Stripe, but there are multiple invoices. No new invoice is be created. Transaction ID: %2", $humanReadableAmount, $paymentIntentId);
            $order->addStatusToHistory(false, $comment, $isCustomerNotified = false);
            $this->orderHelper->saveOrder($order);
            return;
        }

        // If the invoice amount does not match the order amount, do nothing
        $invoice = $invoices->getFirstItem();
        if ($invoice && $invoice->getId())
        {
            $invoiceAmount = $invoice->getGrandTotal();
            if ($invoiceAmount != $order->getGrandTotal())
            {
                $comment = __("Captured %1 via Stripe, but the invoice amount does not match the order amount. Transaction ID: %2", $humanReadableAmount, $paymentIntentId);
                $order->addStatusToHistory(false, $comment, $isCustomerNotified = false);
                $this->orderHelper->saveOrder($order);
                return;
            }

            // If the invoice is paid or canceled, do nothing
            if ($invoice->getState() == \Magento\Sales\Model\Order\Invoice::STATE_PAID)
            {
                $comment = __("Captured %1 via Stripe, but the invoice is already paid. No new invoice will be created. Transaction ID: %2", $humanReadableAmount, $paymentIntentId);
                $order->addStatusToHistory(false, $comment, $isCustomerNotified = false);
                $this->orderHelper->saveOrder($order);
                return;
            }
            else if ($invoice->getState() == \Magento\Sales\Model\Order\Invoice::STATE_CANCELED)
            {
                $comment = __("Captured %1 via Stripe, but the invoice is already canceled. Transaction ID: %2", $humanReadableAmount, $paymentIntentId);
                $order->addStatusToHistory(false, $comment, $isCustomerNotified = false);
                $this->orderHelper->saveOrder($order);
                return;
            }
        }

        $comment = __("%1 amount of %2 via Stripe. Transaction ID: %3", __("Captured"), $humanReadableAmount, $paymentIntentId);
        $order->addStatusToHistory(false, $comment, $isCustomerNotified = false);

        // There is no invoice, or there is an open invoice
        $captureCase = \Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE;
        $this->helper->invoiceOrder($order, $paymentIntentId, $captureCase, true);
    }
}