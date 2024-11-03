<?php

namespace StripeIntegration\Tax\Observer;

use \Magento\Framework\Event\ObserverInterface;
use \Magento\Framework\Event\Observer;
use StripeIntegration\Tax\Exceptions\InvoiceException;
use StripeIntegration\Tax\Model\StripeTransaction;
use Magento\SalesSequence\Model\Manager;
use StripeIntegration\Tax\Model\TaxFlow;

class AddTransactionIdToInvoice implements ObserverInterface
{
    private $stripeTransaction;
    private $sequenceManager;
    private $taxFlow;

    public function __construct(
        StripeTransaction $stripeTransaction,
        Manager $sequenceManager,
        TaxFlow $taxFlow
    )
    {
        $this->stripeTransaction = $stripeTransaction;
        $this->sequenceManager = $sequenceManager;
        $this->taxFlow = $taxFlow;
    }

    public function execute(Observer $observer)
    {
        if ($this->stripeTransaction->isEnabled()) {
            $invoice = $observer->getEvent()->getInvoice();
            if ($invoice->getStripeTaxCalculationId() && !$invoice->getStripeTaxTransactionId()) {
                // If there is no increment id set on the invoice, we set it here to be able to use it as the
                // reference. During the save process, the invoice object is checked for an increment id and if it is
                // set, it will not be set anymore.
                if (!$invoice->getIncrementId()) {
                    $invoice->setIncrementId(
                        $this->sequenceManager->getSequence($invoice->getEntityType(), $invoice->getStoreId())->getNextValue()
                    );
                }
                $transactionId = $this->stripeTransaction->createTransaction($invoice);
                $invoice->setStripeTaxTransactionId($transactionId);
            }

            if (!$this->taxFlow->canInvoiceProceed() && !$invoice->getId()) {
                throw new InvoiceException(__('Invoice could not be created.'));
            }
        }
    }
}