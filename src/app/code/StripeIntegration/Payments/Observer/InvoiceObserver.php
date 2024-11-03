<?php

namespace StripeIntegration\Payments\Observer;

use Magento\Payment\Observer\AbstractDataAssignObserver;

class InvoiceObserver extends AbstractDataAssignObserver
{
    /**
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $eventName = $observer->getEvent()->getName();

        switch($eventName)
        {
            case "sales_invoice_pay":
                break;
            case "sales_order_invoice_save_after":
                break;
            default:
                break;
        }
    }
}
