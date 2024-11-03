<?php

declare(strict_types=1);

namespace StripeIntegration\Payments\Helper;

class Invoice
{
    private $invoiceRepository;

    public function __construct(
        \Magento\Sales\Api\InvoiceRepositoryInterface $invoiceRepository
    )
    {
        $this->invoiceRepository = $invoiceRepository;
    }

    public function saveInvoice($invoice)
    {
        $this->invoiceRepository->save($invoice);
    }
}