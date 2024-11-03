<?php

namespace StripeIntegration\Tax\Helper;

class Invoice
{
    public function canIncludeShipping($invoice)
    {
        // Check shipping amount in previous invoices
        foreach ($invoice->getOrder()->getInvoiceCollection() as $previousInvoice) {
            if (($previousInvoice->getId() != $invoice->getId()) &&
                $previousInvoice->getShippingAmount() &&
                !$previousInvoice->isCanceled()
            ) {
                return false;
            }
        }

        return true;
    }
}