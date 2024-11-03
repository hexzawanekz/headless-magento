<?php

namespace StripeIntegration\Payments\Block\PaymentInfo;

use Magento\Payment\Block\ConfigurableInfo;

class Invoice extends ConfigurableInfo
{
    private $invoice = null;
    private $invoiceFactory;
    private $helper;
    private $paymentsConfig;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Payment\Gateway\ConfigInterface $config,
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Model\Stripe\InvoiceFactory $invoiceFactory,
        \StripeIntegration\Payments\Model\Config $paymentsConfig,
        array $data = []
    ) {
        parent::__construct($context, $config, $data);

        $this->helper = $helper;
        $this->invoiceFactory = $invoiceFactory;
        $this->paymentsConfig = $paymentsConfig;
    }

    public function getInvoice()
    {
        if ($this->invoice)
            return $this->invoice;

        $info = $this->getInfo();
        $invoiceId = $info->getAdditionalInformation('invoice_id');
        $invoice = $this->invoiceFactory->create()->load($invoiceId);
        return $this->invoice = $invoice;
    }

    public function getCustomerUrl()
    {
        $stripeInvoiceModel = $this->getInvoice();
        return $this->helper->getStripeUrl($stripeInvoiceModel->getStripeObject()->livemode, 'customers', $stripeInvoiceModel->getStripeObject()->customer);
    }

    public function getTemplate()
    {
        if (!$this->paymentsConfig->getStripeClient())
            return null;

        return 'paymentInfo/invoice.phtml';
    }

    public function getDateDue()
    {
        $invoice = $this->getInvoice()->getStripeObject();

        $date = $invoice->due_date;

        return date('j M Y', $date);
    }

    public function getStatus()
    {
        $invoice = $this->getInvoice()->getStripeObject();

        return ucfirst($invoice->status);
    }
}
