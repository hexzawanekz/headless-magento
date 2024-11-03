<?php

namespace StripeIntegration\Tax\Test\Integration\Helper;

use Magento\Sales\Model\OrderFactory;

class Tests
{
    private $objectManager;
    private $invoiceService;
    private $orderHelper;
    private $invoiceHelper;

    public function __construct()
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->invoiceService = $this->objectManager->get(\Magento\Sales\Model\Service\InvoiceService::class);
        $this->orderHelper = new \StripeIntegration\Tax\Test\Integration\Helper\Order();
        $this->invoiceHelper = new \StripeIntegration\Tax\Test\Integration\Helper\Invoice();
    }

    public function invoiceOnline($order, $itemQtys, $captureCase = \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE)
    {
        $orderItemIDs = [];
        $orderItemQtys = [];

        foreach ($order->getAllVisibleItems() as $orderItem)
        {
            $orderItemIDs[$orderItem->getSku()] = $orderItem->getId();
        }

        foreach ($itemQtys as $sku => $qty)
        {
            if (isset($orderItemIDs[$sku]))
            {
                $id = $orderItemIDs[$sku];
                $orderItemQtys[$id] = $qty;
            }
        }

        $invoice = $this->invoiceService->prepareInvoice($order, $orderItemQtys);
        $invoice->setRequestedCaptureCase($captureCase);
        $order->setIsInProcess(true);
        $invoice->register();
        $invoice->pay();
        $this->orderHelper->saveOrder($order);

        return $this->invoiceHelper->saveInvoice($invoice);
    }


}