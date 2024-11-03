<?php

namespace StripeIntegration\Payments\Model\Stripe;

class Invoice
{
    use StripeObjectTrait;

    private $objectSpace = 'invoices';
    private $helper;
    private $config;

    public function __construct(
        \StripeIntegration\Payments\Model\Stripe\Service\StripeObjectServicePool $stripeObjectServicePool,
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Model\Config $config
    )
    {
        $stripeObjectService = $stripeObjectServicePool->getStripeObjectService($this->objectSpace);
        $this->setData($stripeObjectService);

        $this->helper = $helper;
        $this->config = $config;
    }

    public function fromOrder($order, $customerId)
    {
        $daysDue = $order->getPayment()->getAdditionalInformation('days_due');

        if (!is_numeric($daysDue))
            $this->helper->throwError("You have specified an invalid value for the invoice due days field.");

        if ($daysDue < 1)
            $this->helper->throwError("The invoice due days must be greater or equal to 1.");

        $data = [
            'customer' => $customerId,
            'collection_method' => 'send_invoice',
            'description' => __("Order #%1 by %2", $order->getRealOrderId(), $order->getCustomerName()),
            'days_until_due' => $daysDue,
            'metadata' => [
                'Order #' => $order->getIncrementId()
            ]
        ];

        try
        {
            $this->createObject($data);
        }
        catch (\Exception $e)
        {
            throw new \Magento\Framework\Exception\LocalizedException(__("The invoice for order #%1 could not be created in Stripe: %2", $order->getIncrementId(), $e->getMessage()));
        }

        return $this;
    }

    public function finalize()
    {
        $this->config->getStripeClient()->invoices->finalizeInvoice($this->getStripeObject()->id, []);

        return $this;
    }
}
