<?php

namespace StripeIntegration\Tax\Helper;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Tax\Api\TaxClassRepositoryInterface;

class LineItems
{
    public const GIFT_CARD_STRIPE_TAX_CODE = 'txcd_10502000';
    public const PRODUCT_TYPE_GIFTCARD = 'giftcard';

    private $currencyHelper;
    private $configHelper;
    private $taxClassRepository;
    private $logger;
    private $orderItemHelper;

    public function __construct(
        Currency $currencyHelper,
        Config $configHelper,
        TaxClassRepositoryInterface $taxClassRepository,
        Logger $logger,
        OrderItem $orderItemHelper
    )
    {
        $this->currencyHelper = $currencyHelper;
        $this->configHelper = $configHelper;
        $this->taxClassRepository = $taxClassRepository;
        $this->logger = $logger;
        $this->orderItemHelper = $orderItemHelper;
    }
    public function getTaxCode($item)
    {
        return $this->getTaxCodeForProduct($item->getProduct());
    }

    public function getTaxCodeForInvoiceTax($item)
    {
        return $this->getTaxCodeForProduct($item->getOrderItem()->getProduct());
    }

    private function getTaxCodeForProduct($product)
    {
        if ($product->getTypeId() == self::PRODUCT_TYPE_GIFTCARD) {
            return self::GIFT_CARD_STRIPE_TAX_CODE;
        }

        return $this->getTaxCodeByTaxClassId($product->getTaxClassId());
    }

    public function getTaxCodeByTaxClassId($taxClassId)
    {
        try {
            $taxClass = $this->taxClassRepository->get($taxClassId);

            if ($taxClass->getStripeProductTaxCode()) {
                return $taxClass->getStripeProductTaxCode();
            }
        } catch (NoSuchEntityException $e) {
            $this->logger->logError($e->getMessage(), $e->getTraceAsString());
        }

        return null;
    }

    public function getAmount($item, $currency)
    {
        $amount = $item->getRowTotal();

        if ($this->configHelper->getCoreTaxConfig()->priceIncludesTax()) {
            $amount = $item->getRowTotalInclTax();
        }

        if ($item->getDiscountAmount() > 0) {
            $amount -= $item->getDiscountAmount();
        }

        return $this->getStripeFormattedAmount($amount, $currency);
    }

    public function getReference($item)
    {
        if ($item->getItemId()) {
            return  $item->getItemId();
        }

        $reference = $item->getSku();

        if ($item->getParentItem()) {
            $reference .= '_' . $item->getParentItem()->getProductId();
        }

        return $reference;
    }

    /**
     * @param $item
     * @param $order
     * @return string
     *
     * If we are in a scenario where invoice is calculated at the point of order creation (authorize and capture),
     * we need to have this reference as something that already exists, so to make it unique we will have the
     * combination of order increment id and product sku
     */
    public function getReferenceForInvoiceTax($item, $order)
    {
        $reference = sprintf('%s_%s', $order->getIncrementId(), $item->getSku());

        if ($item->getOrderItem()->getParentItem()) {
            $reference .= '_' . $item->getOrderItem()->getParentItem()->getProductId();
        }

        if ($this->orderItemHelper->hasCustomizableOptions($item->getOrderItem())) {
            $reference .= $this->orderItemHelper->getCustomizableOptionsSuffix($item->getOrderItem());
        }

        // Stripe limit is 500
        return substr($reference, 0, 500);
    }

    public function getReferenceForInvoiceAdditionalFee($item, $order, $code)
    {
        $reference = $this->getReferenceForInvoiceTax($item, $order);

        // Take out the number of characters equivalent to the length of the code param and then add the param
        // to the end of the reference.
        return substr($reference, 0, 500 - strlen($code) - 1) . '_' . $code;
    }

    public function getLineItemByReference($reference, $lineItems)
    {
        $matchedKey = array_search($reference, array_column($lineItems, 'reference'));
        if ($matchedKey !== false) {
            return $lineItems[$matchedKey];
        }

        return [];
    }

    public function getStripeFormattedAmount($amount, $currency)
    {
        return $this->currencyHelper->magentoAmountToStripeAmount($amount, $currency);
    }

    /**
     * Added to have a general method for creating additional fee references which can be used to link
     * Stripe calculations with Magento tax calculation items
     *
     * @param $item
     * @param $code
     * @return string
     */
    public function getItemAdditionalFeeReference($item, $code)
    {
        return $this->getReference($item) . '_' . $code;
    }

    /**
     * Added to have a general method for creating additional fee references which can be used to link
     *  Stripe calculations with Magento tax calculation items
     *
     * @param $entity
     * @param $code
     * @return string
     */
    public function getSalesEntityAdditionalFeeReference($entity, $code)
    {
        return $this->getSalesEntityId($entity) . '_' . $code;
    }

    private function getSalesEntityId($object)
    {
        $id = $object->getIncrementId();
        if (!$id) {
            $id = $object->getId();
        }

        return $id;
    }
}