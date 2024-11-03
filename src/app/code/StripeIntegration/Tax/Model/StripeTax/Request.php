<?php

namespace StripeIntegration\Tax\Model\StripeTax;

use Magento\Framework\Event\ManagerInterface;
use StripeIntegration\Tax\Exceptions\IpNotFoundException;
use StripeIntegration\Tax\Exceptions\LocalIpException;
use StripeIntegration\Tax\Helper\GiftOptions;
use StripeIntegration\Tax\Model\AdditionalFees\ItemAdditionalFees;
use StripeIntegration\Tax\Model\AdditionalFees\SalesEntityAdditionalFees;
use StripeIntegration\Tax\Model\StripeTax\Request\Cache;
use StripeIntegration\Tax\Model\StripeTax\Request\CustomerDetails;
use StripeIntegration\Tax\Model\StripeTax\Request\LineItem;
use StripeIntegration\Tax\Model\StripeTax\Request\ShippingCost;

class Request
{
    public const CURRENCY_KEY = 'currency';
    public const CUSTOMER_DETAILS_KEY = 'customer_details';
    public const LINE_ITEMS_KEY = 'line_items';
    public const SHIPPING_COST_KEY = 'shipping_cost';
    public const TAX_DATE_KEY = 'tax_date';
    public const EXPAND_RESPONSE_ITEMS_KEY = 'expand';

    private $currency;
    private $customerDetails;
    private $lineItems = [];
    private $shippingCost;
    private $expandResponseItems = [
        'line_items',
        'line_items.data.tax_breakdown',
        'shipping_cost.tax_breakdown'
    ];

    private $requestHelper;
    private $lineItem;
    private $requestCache;
    private $counter;
    private $giftOptionsHelper;
    private $taxDate;
    private $eventManager;
    private $itemAdditionalFees;
    private $salesEntityAdditionalFees;

    public function __construct(
        CustomerDetails $customerDetails,
        LineItem $lineItem,
        ShippingCost $shippingCost,
        \StripeIntegration\Tax\Helper\Request $requestHelper,
        Cache $requestCache,
        GiftOptions $giftOptionsHelper,
        ManagerInterface $eventManager,
        ItemAdditionalFees $itemAdditionalFees,
        SalesEntityAdditionalFees $salesEntityAdditionalFees
    )
    {
        $this->customerDetails = $customerDetails;
        $this->lineItem = $lineItem;
        $this->shippingCost = $shippingCost;
        $this->requestHelper = $requestHelper;
        $this->requestCache = $requestCache;
        $this->giftOptionsHelper = $giftOptionsHelper;
        $this->eventManager = $eventManager;
        $this->itemAdditionalFees = $itemAdditionalFees;
        $this->salesEntityAdditionalFees = $salesEntityAdditionalFees;
    }

    private function formCurrencyDetails($quote)
    {
        $currencyCode =  $this->requestHelper->getCurrency();
        $this->setCurrency($currencyCode);
    }

    private function formCurrencyDetailsForInvoiceTax($order)
    {
        $this->setCurrency($order->getOrderCurrencyCode());
    }

    private function formCustomerDetails($shippingAssignment, $quote)
    {
        $address = $shippingAssignment->getShipping()->getAddress();
        $this->customerDetails->formData($address, $quote);
    }

    public function formCustomerDetailsForInvoiceTax($order)
    {
        $this->customerDetails->formDataForInvoiceTax($order);
    }

    private function formLineItems($quote, $items, $currency, $total)
    {
        $this->clearLineItems();
        foreach ($items as $item) {
            if ($item->getParentItem()) {
                continue;
            }

            $this->addLineItem($this->lineItem->formData($item, $currency)->toArray(), $item->getProductId());
            if ($this->giftOptionsHelper->itemHasGiftOptions($item)) {
                $this->addLineItem(
                    $this->lineItem->formItemGiftOptionsData($item, $currency)->toArray(),
                    $item->getProductId()
                );
            }

            $this->handleItemAdditionalFees($item, $quote, $currency);

            if ($item->getHasChildren() && $item->isChildrenCalculated()) {
                foreach ($item->getChildren() as $childItem) {
                    $parentQty = $item->getQty();
                    $this->addLineItem($this->lineItem->formData($childItem, $currency, $parentQty)->toArray(), $childItem->getProductId());
                    $this->handleItemAdditionalFees($childItem, $quote, $currency);
                }
            }
        }

        if ($this->giftOptionsHelper->salesObjectHasGiftOptions($quote)) {
            $this->addLineItem(
                $this->lineItem->formSalesObjectGiftOptionsData($quote, $currency)->toArray(),
                $quote->getId()
            );
        }

        if ($this->giftOptionsHelper->salesObjectHasPrintedCard($quote)) {
            $this->addLineItem(
                $this->lineItem->formSalesObjectPrintedCardData($quote, $currency)->toArray(),
                $quote->getId()
            );
        }

        $this->handleQuoteAdditionalFees($quote, $total, $currency);
    }

    public function formLineItemsForInvoiceTax($order, $invoice)
    {
        $this->clearLineItems();
        foreach ($invoice->getAllItems() as $item) {
            $orderItem = $item->getOrderItem();
            $orderItemQty = $orderItem->getQtyOrdered();

            if ($orderItemQty) {
                if ($orderItem->isDummy() || $item->getQty() <= 0) {
                    // in case the order item is a bundle dynamic product, add the GW options to the request before skipping
                    if ($orderItem->getHasChildren() &&
                        $orderItem->isChildrenCalculated()
                    ) {
                        if ($this->giftOptionsHelper->itemHasGiftOptions($orderItem)) {
                            $this->addLineItem(
                                $this->lineItem->formItemGwDataForInvoiceTax($item, $order)->toArray(),
                                $item->getProductId()
                            );
                        }

                        $this->handleInvoiceItemAdditionalFees($item, $order, $invoice);
                    }

                    continue;
                }
                $this->addLineItem(
                    $this->lineItem->formDataForInvoiceTax($item, $order)->toArray(), $item->getProductId()
                );
                if ($this->giftOptionsHelper->itemHasGiftOptions($item->getOrderItem())) {
                    $this->addLineItem(
                        $this->lineItem->formItemGwDataForInvoiceTax($item, $order)->toArray(),
                        $item->getProductId()
                    );
                }

                $this->handleInvoiceItemAdditionalFees($item, $order, $invoice);
            }
        }

        if ($this->giftOptionsHelper->salesObjectHasGiftOptions($order) &&
            $order->getGwTaxAmount() != $order->getGwTaxAmountInvoiced()
        ) {
            $this->addLineItem(
                $this->lineItem->formSalesObjectGiftOptionsData($order, $order->getOrderCurrencyCode())->toArray(),
                $order->getId()
            );
        }

        if ($this->giftOptionsHelper->salesObjectHasPrintedCard($order) &&
            $order->getGwCardTaxAmount() != $order->getGwCardTaxInvoiced()
        ) {
            $this->addLineItem(
                $this->lineItem->formSalesObjectPrintedCardData($order, $order->getOrderCurrencyCode())->toArray(),
                $order->getId()
            );
        }
        $this->handleInvoiceAdditionalFees($order, $invoice);
    }

    private function addLineItem($lineItem, $productId = null)
    {
        if ($productId) {
            $this->lineItems[$productId . '_' . $this->counter++] = $lineItem;
        } else {
            $this->lineItems[] = $lineItem;
        }
    }

    private function clearLineItems()
    {
        $this->lineItems = [];
        $this->counter = 0;
    }

    private function formShippingCost($total, $currency)
    {
        $this->shippingCost->formData($total, $currency);
    }

    private function formShippingCostForInvoiceTax($order, $invoice) {
        $this->shippingCost->formDataForInvoiceTax($order, $invoice);
    }

    public function formData($quote, $shippingAssignment, $total)
    {
        $this->formCurrencyDetails($quote);
        $this->formCustomerDetails($shippingAssignment, $quote);
        $this->formLineItems($quote, $shippingAssignment->getItems(), $this->getCurrency(), $total);
        $this->formShippingCost($total, $this->getCurrency());

        $this->validate();

        return $this;
    }

    public function formDataForInvoiceTax($order, $invoice)
    {
        $this->formCurrencyDetailsForInvoiceTax($order);
        $this->formCustomerDetailsForInvoiceTax($order);
        $this->formLineItemsForInvoiceTax($order, $invoice);
        $this->formShippingCostForInvoiceTax($order, $invoice);
        $this->taxDate = time();

        return $this;
    }

    public function toArray()
    {
        $request = [
            self::CURRENCY_KEY => strtolower($this->getCurrency()),
            self::CUSTOMER_DETAILS_KEY => $this->getCustomerDetails()->toArray(),
            self::LINE_ITEMS_KEY => $this->getLineItems(),
            self::SHIPPING_COST_KEY => $this->getShippingCost()->toArray(),
            self::EXPAND_RESPONSE_ITEMS_KEY => $this->getExpandResponseItems()
        ];

        if ($this->taxDate) {
            $request[self::TAX_DATE_KEY] = $this->taxDate;
        }

        $this->requestCache->setRequest($request);

        // Stripe requires the keys of the line items to be numeric and starting from 0
        $request[self::LINE_ITEMS_KEY] = array_values($this->getLineItems());

        return $request;
    }

    private function validate()
    {
        if ($this->getCustomerDetails()->getIpAddress() === false) {
            throw new IpNotFoundException(__('The IP of the user was not fount.'));
        }
        if ($this->getCustomerDetails()->getIpAddress() &&
            preg_match('/^(127\.|10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/', $this->getCustomerDetails()->getIpAddress())
        ) {
            throw new LocalIpException(__('Tax calculation skipped. IP address is local.'));
        }
    }

    private function handleItemAdditionalFees($item, $quote, $currency, $parentQty = 1)
    {
        $this->eventManager->dispatch(
            'stripe_tax_additional_fee_item',
            [
                'item' => $item,
                'quote' => $quote,
                'additional_fees_container' => $this->itemAdditionalFees->clearValues()
            ]
        );
        foreach ($this->itemAdditionalFees->getAdditionalFees() as $additionalFee) {
            $this->addLineItem(
                $this->lineItem->formAdditionalFeeItemData($item, $currency, $additionalFee, $parentQty)->toArray(),
                $item->getProductId()
            );
        }
    }

    private function handleQuoteAdditionalFees($quote, $total, $currency)
    {
        $this->eventManager->dispatch(
            'stripe_tax_additional_fee_quote',
            [
                'quote' => $quote,
                'total' => $total,
                'additional_fees_container' => $this->salesEntityAdditionalFees->clearValues()
            ]
        );

        foreach ($this->salesEntityAdditionalFees->getAdditionalFees() as $additionalFee) {
            $this->addLineItem(
                $this->lineItem->formAdditionalFeeSalesEntityData($quote, $currency, $additionalFee)->toArray(),
                $quote->getId()
            );
        }
    }

    private function handleInvoiceItemAdditionalFees($item, $order, $invoice)
    {
        $this->eventManager->dispatch(
            'stripe_tax_additional_fee_invoice_item',
            [
                'item' => $item,
                'invoice' => $invoice,
                'additional_fees_container' => $this->itemAdditionalFees->clearValues()
            ]
        );

        $itemAdditionalFees = [];
        foreach ($this->itemAdditionalFees->getAdditionalFees() as $additionalFee) {
            $this->addLineItem(
                $this->lineItem->formAdditionalFeeInvoiceItemData($item, $order, $additionalFee)->toArray(),
                $item->getProductId()
            );
            $itemAdditionalFees[] = $additionalFee['code'];
        }
        $item->setAdditionalFees($itemAdditionalFees);
    }

    private function handleInvoiceAdditionalFees($order, $invoice)
    {
        $this->eventManager->dispatch(
            'stripe_tax_additional_fee_invoice',
            [
                'invoice' => $invoice,
                'order' => $order,
                'additional_fees_container' => $this->salesEntityAdditionalFees->clearValues()
            ]
        );

        $additionalFees = [];
        foreach ($this->salesEntityAdditionalFees->getAdditionalFees() as $additionalFee) {
            $this->addLineItem(
                $this->lineItem->formAdditionalFeeSalesEntityData($order, $order->getOrderCurrencyCode(), $additionalFee)->toArray(),
                $order->getId()
            );
            $additionalFees[] = $additionalFee['code'];
        }
        $invoice->setAdditionalFees($additionalFees);
    }

    public function getCurrency()
    {
        return $this->currency;
    }

    public function getCustomerDetails()
    {
        return $this->customerDetails;
    }

    public function getLineItems()
    {
        return $this->lineItems;
    }

    public function getShippingCost()
    {
        return $this->shippingCost;
    }

    public function getExpandResponseItems()
    {
        return $this->expandResponseItems;
    }

    public function setCurrency($currency)
    {
        $this->currency = $currency;

        return $this;
    }

    public function setCustomerDetails($customerDetails)
    {
        $this->customerDetails = $customerDetails;

        return $this;
    }

    public function setLineItems($lineItems)
    {
        $this->lineItems = $lineItems;

        return $this;
    }

    public function setShippingCost($shippingCost)
    {
        $this->shippingCost = $shippingCost;

        return $this;
    }
}