<?php

namespace StripeIntegration\Tax\Plugin\Sales\Model;

use Magento\Sales\Api\OrderRepositoryInterface;
use StripeIntegration\Tax\Model\Order\StripeDataManagement;
use Magento\Sales\Model\Order;

class OrderRepository
{
    private $stripeDataManagement;

    public function __construct(StripeDataManagement $stripeDataManagement)
    {
        $this->stripeDataManagement = $stripeDataManagement;
    }

    public function beforeSave(OrderRepositoryInterface $subject, Order $order)
    {
        return [$this->stripeDataManagement->setDataFrom($order)];
    }
}