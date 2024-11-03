<?php

namespace StripeIntegration\Tax\Test\Integration\Helper;

class Invoice
{
    private $objectManager;
    private $invoiceRepository;

    public function __construct()
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->invoiceRepository = $this->objectManager->get(\Magento\Sales\Api\InvoiceRepositoryInterface::class);
    }

    public function getInvoiceItem($invoice, $sku)
    {
        foreach ($invoice->getAllItems() as $invoiceItem)
        {
            if ($invoiceItem->getSku() == $sku)
                return $invoiceItem;
        }

        return null;
    }

    public function saveInvoice($invoice)
    {
        return $this->invoiceRepository->save($invoice);
    }
}