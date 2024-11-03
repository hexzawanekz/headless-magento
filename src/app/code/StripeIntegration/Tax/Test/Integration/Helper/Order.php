<?php

namespace StripeIntegration\Tax\Test\Integration\Helper;

class Order
{
    private $objectManager;
    private $orderFactory;
    private $orderRepository;

    public function __construct()
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->orderFactory = $this->objectManager->get(\Magento\Sales\Model\OrderFactory::class);
        $this->orderRepository = $this->objectManager->get(\Magento\Sales\Api\OrderRepositoryInterface::class);
    }

    public function refreshOrder($order)
    {
        if (!$order->getId())
            throw new \Exception("No order ID provided");

        return $this->orderFactory->create()->load($order->getId());
    }

    public function getOrderItem($order, $sku)
    {
        foreach ($order->getAllItems() as $orderItem)
        {
            if ($orderItem->getSku() == $sku)
                return $orderItem;
        }

        return null;
    }

    public function saveOrder($order)
    {
        return $this->orderRepository->save($order);
    }
}