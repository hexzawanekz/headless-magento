<?php

namespace StripeIntegration\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;

class CancelInvoice implements ObserverInterface
{
    private $helper;
    private $config;

    public function __construct(
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Model\Config $config
    )
    {
        $this->helper = $helper;
        $this->config = $config;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $payment = $observer->getPayment();
        $method = $payment->getMethod();

        if ($method != 'stripe_payments_invoice')
        {
            return;
        }

        if (!$this->helper->isAdmin())
        {
            return;
        }

        $invoice = $observer->getInvoice();
        $order = $invoice->getOrder();
        $invoiceId = $payment->getAdditionalInformation('invoice_id');

        try
        {
            $this->config->getStripeClient()->invoices->voidInvoice($invoiceId, []);
        }
        catch (\Exception $e)
        {
            $this->helper->throwError($e->getMessage());
        }
    }
}
