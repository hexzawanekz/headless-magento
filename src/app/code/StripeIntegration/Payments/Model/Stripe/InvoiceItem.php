<?php

namespace StripeIntegration\Payments\Model\Stripe;

class InvoiceItem
{
    use StripeObjectTrait;

    private $objectSpace = 'invoiceItems';
    private $helper;

    public function __construct(
        \StripeIntegration\Payments\Model\Stripe\Service\StripeObjectServicePool $stripeObjectServicePool,
        \StripeIntegration\Payments\Helper\Generic $helper
    )
    {
        $stripeObjectService = $stripeObjectServicePool->getStripeObjectService($this->objectSpace);
        $this->setData($stripeObjectService);

        $this->helper = $helper;
    }

    public function fromOrderGrandTotal($order, $customerId)
    {
        $data = [
            'customer' => $customerId,
            'unit_amount' => $this->helper->convertMagentoAmountToStripeAmount($order->getGrandTotal(), $order->getOrderCurrencyCode()),
            'currency' => $order->getOrderCurrencyCode(),
            'description' => __("Order #%1", $order->getIncrementId()),
            'quantity' => 1
        ];

        try
        {
            $this->createObject($data);
        }
        catch (\Exception $e)
        {
            throw new \Magento\Framework\Exception\LocalizedException(__("The invoice item could not be created in Stripe: %1", $e->getMessage()));
        }

        return $this;
    }

    public function fromOrderItem($item, $order, $customerId)
    {
        $data = [
            'customer' => $customerId,
            'price_data' => [
                'currency' => $order->getOrderCurrencyCode(),
                'product' => $item->getProductId(),
                'unit_amount' => $this->helper->convertMagentoAmountToStripeAmount($item->getPrice(), $order->getOrderCurrencyCode())
            ],
            'currency' => $order->getOrderCurrencyCode(),
            'description' => $item->getName(),
            'quantity' => $item->getQtyOrdered()
        ];

        try
        {
            $this->createObject($data);
        }
        catch (\Exception $e)
        {
            throw new \Magento\Framework\Exception\LocalizedException(__("The invoice item for product \"%1\" could not be created in Stripe: %2", $item->getName(), $e->getMessage()));
        }

        return $this;
    }

    public function fromTax($order, $customerId)
    {
        $currency = $order->getOrderCurrencyCode();
        $amount = $this->helper->convertMagentoAmountToStripeAmount($order->getTaxAmount(), $currency);
        if (!$amount || $amount <= 0)
            return $this;

        $data = [
            'customer' => $customerId,
            'amount' => $amount,
            'currency' => $currency,
            'description' => __("Tax")
        ];

        try
        {
            $this->createObject($data);
        }
        catch (\Exception $e)
        {
            throw new \Magento\Framework\Exception\LocalizedException(__("The tax for order #%1 could not be created in Stripe: %2", $order->getIncrementId(), $e->getMessage()));
        }

        return $this;
    }

    public function fromShipping($order, $customerId)
    {
        $currency = $order->getOrderCurrencyCode();
        $amount = $this->helper->convertMagentoAmountToStripeAmount($order->getShippingAmount(), $currency);
        if (!$amount || $amount <= 0)
            return $this;

        $data = [
            'customer' => $customerId,
            'amount' => $amount,
            'currency' => $currency,
            'description' => __("Shipping")
        ];

        try
        {
            $this->createObject($data);
        }
        catch (\Exception $e)
        {
            throw new \Magento\Framework\Exception\LocalizedException(__("The shipping amount for order #%1 could not be created in Stripe: %2", $order->getIncrementId(), $e->getMessage()));
        }

        return $this;
    }
}
