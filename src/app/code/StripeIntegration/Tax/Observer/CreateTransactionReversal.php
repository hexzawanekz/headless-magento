<?php

namespace StripeIntegration\Tax\Observer;

use \Magento\Framework\Event\ObserverInterface;
use \Magento\Framework\Event\Observer;
use Magento\Framework\Serialize\SerializerInterface;
use StripeIntegration\Tax\Exceptions\CreditmemoException;
use StripeIntegration\Tax\Helper\AreaCode;
use StripeIntegration\Tax\Helper\Creditmemo;
use StripeIntegration\Tax\Model\StripeTransactionReversal;
use Magento\SalesSequence\Model\Manager;
use StripeIntegration\Tax\Helper\Order;
use StripeIntegration\Tax\Model\TaxFlow;

class CreateTransactionReversal implements ObserverInterface
{
    private $stripeTransactionReversal;
    private $sequenceManager;
    private $areaCodeHelper;
    private $creditmemoHelper;
    private $orderHelper;
    private $serializer;
    private $taxFlow;

    public function __construct(
        StripeTransactionReversal $stripeTransactionReversal,
        Manager $sequenceManager,
        AreaCode $areaCodeHelper,
        Creditmemo $creditmemoHelper,
        Order $orderHelper,
        SerializerInterface $serializer,
        TaxFlow $taxFlow
    )
    {
        $this->stripeTransactionReversal = $stripeTransactionReversal;
        $this->sequenceManager = $sequenceManager;
        $this->areaCodeHelper = $areaCodeHelper;
        $this->creditmemoHelper = $creditmemoHelper;
        $this->orderHelper = $orderHelper;
        $this->serializer = $serializer;
        $this->taxFlow = $taxFlow;
    }

    public function execute(Observer $observer)
    {
        $creditMemo = $observer->getEvent()->getCreditmemo();

        // Handles the reversal if the credit memo was started from the invoice page
        // Create reversal only if Stripe tax enabled
        // and there is an invoice for the credit memo
        // and the tax was calculated using Stripe Tax
        if ($this->stripeTransactionReversal->isEnabled() &&
            $creditMemo->getInvoice() &&
            $creditMemo->getInvoice()->getStripeTaxTransactionId() &&
            !$this->orderHelper->isOrderTaxTransactionFullyReversed($creditMemo->getOrder(), $creditMemo->getInvoice()->getStripeTaxTransactionId())
        ) {
            // If there is no increment id set on the credit memo, we set it here to be able to use it as the
            // reference. During the save process, the credit memo object is checked for an increment id and
            // if it is set, it will not be set anymore.
            if (!$creditMemo->getIncrementId()) {
                $creditMemo->setIncrementId(
                    $this->sequenceManager->getSequence($creditMemo->getEntityType(), $creditMemo->getStoreId())->getNextValue()
                );
            }
            $reversalResult = $this->stripeTransactionReversal->createReversal($creditMemo);
            if (!$this->taxFlow->canCreditMemoProceed()) {
                throw new CreditmemoException(__('Credit memo could not be created.'));
            }
            $creditMemo->setStripeTaxTransactionId($reversalResult['transaction_id']);
            $this->orderHelper->addTransactionMode(
                $creditMemo->getOrder(),
                $creditMemo->getInvoice()->getStripeTaxTransactionId(),
                $reversalResult['mode'],
                $reversalResult['transaction_id']
            );
        } elseif ($this->stripeTransactionReversal->isEnabled()) {
            if (!$creditMemo->getIncrementId()) {
                $creditMemo->setIncrementId(
                    $this->sequenceManager->getSequence($creditMemo->getEntityType(), $creditMemo->getStoreId())->getNextValue()
                );
            }
            $transactionIds = [];
            $creditMemo->setAmountToRevert(0);
            foreach ($creditMemo->getOrder()->getInvoiceCollection() as $invoice) {
                if ($invoice->getStripeTaxTransactionId() &&
                    !$this->orderHelper->isOrderTaxTransactionFullyReversed($creditMemo->getOrder(), $invoice->getStripeTaxTransactionId()) &&
                    $creditMemo->getGrandTotal() > $creditMemo->getAmountToRevert()
                ) {
                    $reversalResult = $this->stripeTransactionReversal->createReversal($creditMemo, $invoice);
                    if (!$this->taxFlow->canCreditMemoProceed()) {
                        throw new CreditmemoException(__('Offline credit memo could not be created.'));
                    }
                    $transactionIds[] = $reversalResult['transaction_id'];
                    $this->orderHelper->addTransactionMode(
                        $creditMemo->getOrder(),
                        $invoice->getStripeTaxTransactionId(),
                        $reversalResult['mode'],
                        $reversalResult['transaction_id'],
                        $reversalResult['line_items_data']
                    );
                }
            }
            if ($transactionIds) {
                $creditMemo->setStripeTaxTransactionId($this->serializer->serialize($transactionIds));
            }
        }
    }
}