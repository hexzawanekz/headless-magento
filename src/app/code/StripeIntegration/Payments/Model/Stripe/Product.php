<?php

namespace StripeIntegration\Payments\Model\Stripe;

class Product
{
    use StripeObjectTrait;

    private $objectSpace = 'products';

    public function __construct(
        \StripeIntegration\Payments\Model\Stripe\Service\StripeObjectServicePool $stripeObjectServicePool
    )
    {
        $stripeObjectService = $stripeObjectServicePool->getStripeObjectService($this->objectSpace);
        $this->setData($stripeObjectService);
    }

    public function fromData($id, $name, $metadata = null)
    {
        $data = [
            'name' => $name
        ];

        if (!empty($metadata))
        {
            $data['metadata'] = $metadata;
        }

        try
        {
            $this->upsert($id, $data);
        }
        catch (\Exception $e)
        {
            throw new \Magento\Framework\Exception\LocalizedException(__("The product could not be created in Stripe: %1", $e->getMessage()));
        }

        return $this;
    }

    public function fromOrderItem($orderItem)
    {
        if ($orderItem->getParentItem() && $orderItem->getParentItem()->getName() && $orderItem->getParentItem()->getProductId())
        {
            $name = $orderItem->getParentItem()->getName();
            $productId = $orderItem->getParentItem()->getProductId();
        }
        else
        {
            $name = $orderItem->getName();
            $productId = $orderItem->getProductId();
        }

        $data = [
            'name' => $name
        ];

        try
        {
            $this->upsert($productId, $data);
        }
        catch (\Exception $e)
        {
            throw new \Magento\Framework\Exception\LocalizedException(__("The product \"%1\" could not be created in Stripe: %2", $orderItem->getName(), $e->getMessage()));
        }

        return $this;
    }

    public function fromQuoteItem($quoteItem)
    {
        return $this->fromOrderItem($quoteItem);
    }
}
