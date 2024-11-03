<?php

namespace StripeIntegration\Payments\Observer;

use \Magento\Framework\Event\ObserverInterface;
use \Magento\Framework\Event\Observer;
use StripeIntegration\Payments\Helper\Subscriptions;
use StripeIntegration\Payments\Model\InitialFee;
use StripeIntegration\Payments\Model\SubscriptionProductFactory;

class AddInitialFeeToStripeInvoiceCalculation implements ObserverInterface
{
    private $subscriptionProductFactory;
    private $subscriptionsHelper;

    public function __construct(
        SubscriptionProductFactory $subscriptionProductFactory,
        Subscriptions $subscriptionsHelper
    ) {
        $this->subscriptionProductFactory = $subscriptionProductFactory;
        $this->subscriptionsHelper = $subscriptionsHelper;
    }

    public function execute(Observer $observer)
    {
        $order = $observer->getOrder();
        $additionalFees = $observer->getAdditionalFeesContainer();
        $subscriptionProductDetails = $this->subscriptionsHelper->getSubscriptionProductFromOrder($order);
        if ($subscriptionProductDetails) {
            $product = $subscriptionProductDetails['product'];
            $orderItem = $subscriptionProductDetails['order_item'];

            $itemDetails = [
                'amount' => $orderItem->getInitialFee(),
                'tax_class_id' => $product->getTaxClassId(),
                'code' => InitialFee::INITIAL_FEE_TYPE
            ];

            $additionalFees->addAdditionalFee($itemDetails);
        }
    }
}