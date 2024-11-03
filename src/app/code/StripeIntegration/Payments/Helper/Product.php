<?php

namespace StripeIntegration\Payments\Helper;

use Magento\Framework\Exception\NoSuchEntityException;

class Product
{
    private $productRepository;
    private $storeManager;

    public function __construct(
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
    }

    public function getProduct($productId): \Magento\Catalog\Api\Data\ProductInterface
    {
        if ($this->storeManager->getStore() && $this->storeManager->getStore()->getId())
        {
            $storeId = $this->storeManager->getStore()->getId();
        }
        else
        {
            $storeId = null;
        }

        try
        {
            return $this->productRepository->getById($productId, false, $storeId);
        }
        catch (NoSuchEntityException $e)
        {
            throw new \Magento\Framework\Exception\LocalizedException(
                __("The product wasn't found. Verify the product and try again."),
                $e
            );
        }
    }

    public function saveProduct($product)
    {
        return $this->productRepository->save($product);
    }

    public function requiresShipping($product)
    {
        if ($product->getTypeId() == 'virtual')
        {
            return false;
        }

        if ($product->getTypeId() == 'simple')
        {
            return true;
        }

        if ($product->getTypeId() == 'giftcard')
        {
            if ($product->getGiftcardType() == 1) // Physical gift cards
                return true;
            else if ($product->getGiftcardType() == 2) // Combined gift cards
                return true;
        }

        if ($product->getTypeId() == 'configurable')
        {
            $children = $product->getTypeInstance()->getUsedProducts($product);
            foreach ($children as $child)
            {
                if ($this->requiresShipping($child))
                    return true;
            }
        }

        if ($product->getTypeId() == 'grouped')
        {
            $children = $product->getTypeInstance()->getAssociatedProducts($product);
            foreach ($children as $child)
            {
                if ($this->requiresShipping($child))
                    return true;
            }
        }

        if ($product->getTypeId() == 'bundle')
        {
            $bundleType = $product->getTypeInstance();
            $optionIds = $bundleType->getOptionsIds($product);
            $selections = $bundleType->getSelectionsCollection($optionIds, $product);

            foreach ($selections as $selection)
            {
                if (!$selection->isVirtual())
                {
                    return true;
                }
            }
        }

        return false;
    }

    public function getPrice($product)
    {
        // Simple, virtual, downloadable, giftcard
        if ($product->getTypeId() == 'simple' || $product->getTypeId() == 'virtual' || $product->getTypeId() == 'downloadable' || $product->getTypeId() == 'giftcard')
        {
            return $product->getPrice();
        }

        // Configurable
        if ($product->getTypeId() == 'configurable')
        {
            $children = $product->getTypeInstance()->getUsedProducts($product);
            $minPrice = null;
            foreach ($children as $child)
            {
                if ($minPrice === null || $child->getPrice() < $minPrice)
                    $minPrice = $child->getPrice();
            }
            return $minPrice;
        }

        // Grouped
        if ($product->getTypeId() == 'grouped')
        {
            $children = $product->getTypeInstance()->getAssociatedProducts($product);
            $minPrice = null;
            foreach ($children as $child)
            {
                if ($minPrice === null || $child->getPrice() < $minPrice)
                    $minPrice = $child->getPrice();
            }
            return $minPrice;
        }

        // Bundle
        if ($product->getTypeId() == 'bundle')
        {
            // Get the default price displayed in the product catalog
            $minPrice = $product->getPriceInfo()
                ->getPrice('final_price')
                ->getMinimalPrice()
                ->getValue();

            return $minPrice;
        }

        return 0;
    }

}