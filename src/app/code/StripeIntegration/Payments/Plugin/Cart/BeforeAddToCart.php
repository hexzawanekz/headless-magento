<?php

namespace StripeIntegration\Payments\Plugin\Cart;

class BeforeAddToCart
{
    private $messageManager;
    private $config;
    private $configurableProductFactory;
    private $subscriptionProductFactory;
    private $quoteHelper;

    public function __construct(
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Model\SubscriptionProductFactory $subscriptionProductFactory,
        \StripeIntegration\Payments\Helper\Quote $quoteHelper,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\ConfigurableProduct\Model\Product\Type\ConfigurableFactory $configurableProductFactory
    )
    {
        $this->messageManager = $messageManager;
        $this->config = $config;
        $this->configurableProductFactory = $configurableProductFactory;
        $this->subscriptionProductFactory = $subscriptionProductFactory;
        $this->quoteHelper = $quoteHelper;
    }

    public function beforeAddProduct(
        \Magento\Quote\Model\Quote $quote,
        \Magento\Catalog\Model\Product $product,
        $request = null,
        $processMode = \Magento\Catalog\Model\Product\Type\AbstractType::PROCESS_MODE_FULL
    )
    {
        if (!$this->config->isSubscriptionsEnabled())
        {
            return;
        }

        $subscriptionProductModel = $this->subscriptionProductFactory->create()->fromProductId($product->getId());

        if ($product->getTypeId() == 'bundle')
        {
            // Based on the request, determine which child products are being added
            $bundleOption = $request->getBundleOption();

            foreach ($bundleOption as $optionId => $selectionId)
            {
                if (!is_array($selectionId))
                {
                    $selectionId = [$selectionId];
                }

                foreach ($selectionId as $selId)
                {
                    $selection = $product->getTypeInstance()
                        ->getSelectionsCollection([$optionId], $product)
                        ->getItemById($selId);

                    if ($selection) {
                        $subscriptionProductModel = $this->subscriptionProductFactory->create()
                            ->fromProductId($selection->getProductId());

                        if ($subscriptionProductModel->isSubscriptionProduct()) {
                            return;
                        }
                    }
                }
            }
        }

        if ($product->getTypeId() == 'configurable')
        {
            $product = $this->getConfigurableChildProductFromRequest($product, $request);
        }

        if (!$subscriptionProductModel->isSubscriptionProduct())
        {
            return;
        }

        if ($this->quoteHelper->removeSubscriptions($quote))
        {
            $this->messageManager->addNoticeMessage(__('You can only purchase one subscription at a time.'));
        }

        return null;
    }

    private function getConfigurableChildProductFromRequest($addProduct, $request)
    {
        if (empty($request) || is_numeric($request))
        {
            return $addProduct;
        }

        if ($addProduct->getTypeId() != 'configurable')
        {
            return $addProduct;
        }

        $attributes = $request->getSuperAttribute();
        if (empty($attributes))
        {
            return $addProduct;
        }

        $product = $this->configurableProductFactory->create()->getProductByAttributes($attributes, $addProduct);
        if ($product)
        {
            return $product;
        }

        return $addProduct;
    }
}
