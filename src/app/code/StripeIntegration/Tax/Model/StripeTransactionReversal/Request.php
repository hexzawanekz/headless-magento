<?php

namespace StripeIntegration\Tax\Model\StripeTransactionReversal;



use StripeIntegration\Tax\Helper\Logger;
use StripeIntegration\Tax\Model\StripeTransaction\Transaction;
use StripeIntegration\Tax\Model\StripeTransactionReversal\Request\LineItems;
use StripeIntegration\Tax\Model\StripeTransactionReversal\Request\ShippingCost;
use StripeIntegration\Tax\Model\Config;
use StripeIntegration\Tax\Helper\Order;

class Request
{
    public const ORIGINAL_TRANSACTION_FIELD_NAME = 'original_transaction';
    public const REFERENCE_FIELD_NAME = 'reference';
    public const MODE_FIELD_NAME = 'mode';
    public const EXPAND_FIELD_NAME = 'expand';
    public const SHIPPING_COST_FIELD_NAME = 'shipping_cost';
    public const LINE_ITEMS_FIELD_NAME = 'line_items';
    public const MODE_FULL = 'full';
    public const MODE_PARTIAL = 'partial';

    private $mode;
    private $originalTransaction;
    private $reference;
    private $expand;
    private $shippingCost;
    private $lineItems;
    private $config;
    private $logger;
    private $transaction;
    private $orderHelper;
    private $transactionReverted;

    public function __construct(
        ShippingCost $shippingCost,
        LineItems $lineItems,
        Config $config,
        Logger $logger,
        Transaction $transaction,
        Order $orderHelper
    )
    {
        $this->shippingCost = $shippingCost;
        $this->lineItems = $lineItems;
        $this->config = $config;
        $this->logger = $logger;
        $this->transaction = $transaction;
        $this->orderHelper = $orderHelper;
    }

    /**
     * Checks if the credit memo is for all the invoiced amount of the order
     *
     * @param $creditMemo
     * @return bool
     */
    public function isCreditmemoPartial($creditMemo)
    {
        $order = $creditMemo->getOrder();
        if ($creditMemo->getGrandTotal() != $order->getTotalInvoiced()) {
            return true;
        }

        return false;
    }

    public function isPartial()
    {
        return $this->mode === self::MODE_PARTIAL;
    }

    public function formData($creditMemo, $invoice = null)
    {
        $this->transactionReverted = false;
        $this->mode = self::MODE_FULL;
        $this->expand = ['line_items'];
        if ($invoice) {
            $this->originalTransaction = $invoice->getStripeTaxTransactionId();
            $this->reference = sprintf('%s_%s_%s', $creditMemo->getIncrementId(), $invoice->getIncrementId(), time());
        } else {
            $this->originalTransaction = $creditMemo->getInvoice()->getStripeTaxTransactionId();
            $this->reference = sprintf('%s_%s_%s', $creditMemo->getIncrementId(), $creditMemo->getInvoice()->getIncrementId(), time());
        }

        if ($this->isCreditmemoPartial($creditMemo)) {
            if ($invoice) {
                $transaction = $this->initTransaction($invoice->getStripeTaxTransactionId());
                $latestReversalId = $this->orderHelper->getReversalForInvoiceTransaction($creditMemo->getOrder(), $invoice->getStripeTaxTransactionId());

                $shippingFullyRefunded = $this->shippingCost->formOfflineData($creditMemo, $invoice, $transaction);
                $lineItemsFullyRefunded = $this->lineItems->formOfflineData($creditMemo, $invoice, $transaction);

                $this->mode = self::MODE_PARTIAL;
                // If the transaction has no reversals on it and the shipping and line items were fully reversed,
                // set the reversal request as a full request
                if ($shippingFullyRefunded && $lineItemsFullyRefunded) {
                    $this->transactionReverted = true;
                    if (!$latestReversalId) {
                        $this->mode = self::MODE_FULL;
                    }
                }
            } else {
                $this->mode = self::MODE_PARTIAL;
                $this->shippingCost->formOnlineData($creditMemo);
                $this->lineItems->formOnlineData($creditMemo);
            }
        }

        return $this;
    }

    public function toArray()
    {
        $request = [
            self::MODE_FIELD_NAME => $this->mode,
            self::ORIGINAL_TRANSACTION_FIELD_NAME => $this->originalTransaction,
            self::REFERENCE_FIELD_NAME => $this->reference,
            self::EXPAND_FIELD_NAME => $this->expand
        ];

        // Only add shipping and line items to the request if it is a partial revert
        if ($this->isPartial()) {
            if ($this->shippingCost->canIncludeInRequest()) {
                $request[self::SHIPPING_COST_FIELD_NAME] = $this->shippingCost->toArray();
            }
            if ($this->lineItems->canIncludeInRequest()) {
                $request[self::LINE_ITEMS_FIELD_NAME] = $this->lineItems->toArray();
            }
        }

        return $request;
    }

    public function getTransactionStatus()
    {
        if ($this->transactionReverted) {
            return self::MODE_FULL;
        }

        return $this->mode;
    }

    public function initTransaction($transactionId)
    {
        try {
            $transaction = $this->config->getStripeClient()->tax->transactions->retrieve($transactionId, ['expand' => ['line_items']]);
            if ($transaction->getLastResponse()->code === 200) {
                $this->transaction->setData($transaction);
            }
        } catch (\Exception $e) {
            $this->logger->logError(sprintf('Unable to retrieve transaction %s: ', $transactionId) . $e->getMessage());
        }

        return $this->transaction;
    }

    public function getLineItems()
    {
        return $this->lineItems;
    }
}