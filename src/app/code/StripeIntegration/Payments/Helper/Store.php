<?php

namespace StripeIntegration\Payments\Helper;

class Store
{
    private $backendSessionQuote;
    private $request;
    private $orderRepository;
    private $invoiceRepository;
    private $creditmemoRepository;
    private $shipmentRepository;
    private $storeManager;
    private $areaCodeHelper;

    public function __construct(
        \Magento\Backend\Model\Session\Quote $backendSessionQuote,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Api\InvoiceRepositoryInterface $invoiceRepository,
        \Magento\Sales\Api\CreditmemoRepositoryInterface $creditmemoRepository,
        \Magento\Sales\Api\ShipmentRepositoryInterface $shipmentRepository,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \StripeIntegration\Payments\Helper\AreaCode $areaCodeHelper
    )
    {
        $this->backendSessionQuote = $backendSessionQuote;
        $this->request = $request;
        $this->orderRepository = $orderRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->creditmemoRepository = $creditmemoRepository;
        $this->shipmentRepository = $shipmentRepository;
        $this->storeManager = $storeManager;
        $this->areaCodeHelper = $areaCodeHelper;
    }

    public function getStoreId()
    {
        if ($this->areaCodeHelper->isAdmin())
        {
            if ($this->request->getParam('order_id', null))
            {
                // Viewing an order
                $order = $this->orderRepository->get($this->request->getParam('order_id', null));
                return $order->getStoreId();
            }
            if ($this->request->getParam('invoice_id', null))
            {
                // Viewing an invoice
                $invoice = $this->invoiceRepository->get($this->request->getParam('invoice_id', null));
                return $invoice->getStoreId();
            }
            else if ($this->request->getParam('creditmemo_id', null))
            {
                // Viewing a credit memo
                $creditmemo = $this->creditmemoRepository->get($this->request->getParam('creditmemo_id', null));
                return $creditmemo->getStoreId();
            }
            else if ($this->request->getParam('shipment_id', null))
            {
                // Viewing a shipment
                $shipment = $this->shipmentRepository->get($this->request->getParam('shipment_id', null));
                return $shipment->getStoreId();
            }
            else
            {
                // Creating a new order
                $quote = $this->backendSessionQuote->getQuote();
                return $quote->getStoreId();
            }
        }
        else
        {
            return $this->storeManager->getStore()->getId();
        }
    }

    public function isSecure()
    {
        return $this->storeManager->getStore()->isCurrentlySecure();
    }

    public function getStore($storeId = null)
    {
        return $this->storeManager->getStore($storeId ?? $this->getStoreId());
    }
}