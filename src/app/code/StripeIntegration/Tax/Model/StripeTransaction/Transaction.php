<?php

namespace StripeIntegration\Tax\Model\StripeTransaction;

use Magento\Framework\Serialize\SerializerInterface;
use StripeIntegration\Tax\Helper\LineItems;

class Transaction
{
    private $data;
    private $lineItemsProcessed;
    private $serializer;
    private $lineItemsHelper;

    public function __construct(
        SerializerInterface $serializer,
        LineItems $lineItemsHelper
    )
    {
        $this->serializer  = $serializer;
        $this->lineItemsHelper = $lineItemsHelper;
    }

    public function setData($transactionData)
    {
        if (!is_string($transactionData)) {
            $transactionData = $this->serializer->serialize($transactionData);
        }
        $this->data = $this->serializer->unserialize($transactionData);
        $this->lineItemsProcessed = 0;

        return $this;
    }

    public function getId()
    {
        return $this->data['id'];
    }

    public function hasShippingTax()
    {
        return isset($this->data['shipping_cost']) &&
            $this->data['shipping_cost']['amount'] > 0 &&
            $this->data['shipping_cost']['amount_tax'] > 0;
    }

    public function hasLineItems()
    {
        return isset($this->data['line_items']) &&
            $this->data['line_items']['total_count'] > 0;
    }

    public function getShippingAmount()
    {
        return $this->data['shipping_cost']['amount'];
    }

    public function getShippingTaxAmount()
    {
        return $this->data['shipping_cost']['amount_tax'];
    }

    public function getLineItemByCreditMemoItem($creditMemoItem, $order)
    {
        $reference = $this->lineItemsHelper->getReferenceForInvoiceTax($creditMemoItem, $order);

        return $this->lineItemsHelper->getLineItemByReference($reference, $this->data['line_items']['data']);
    }

    /**
     * Will be called after each line item is processed to stop looping through credit memo items if the
     * items of a transaction have been processed
     *
     * @return void
     */
    public function addItemProcessed()
    {
        $this->lineItemsProcessed++;
    }

    public function isProcessed()
    {
        return $this->lineItemsProcessed == $this->data['line_items']['total_count'];
    }

    public function getLineItems()
    {
        return $this->data['line_items']['data'];
    }

    public function formLineItemsData()
    {
        $metadata = [];
        foreach ($this->data['line_items']['data'] as $lineItem) {
            $metadata[$lineItem['reference']] = [
                'id' => $lineItem['id'],
                'remaining_amount' => $lineItem['amount'],
                'remaining_amount_tax' => $lineItem['amount_tax'],
            ];
        }

        return $metadata;
    }
}
