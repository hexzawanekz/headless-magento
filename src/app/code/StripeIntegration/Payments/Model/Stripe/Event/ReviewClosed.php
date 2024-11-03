<?php

namespace StripeIntegration\Payments\Model\Stripe\Event;

use StripeIntegration\Payments\Model\Stripe\StripeObjectTrait;

class ReviewClosed
{
    use StripeObjectTrait;

    private $eventManager;
    private $webhooksHelper;
    private $helper;
    private $orderHelper;

    public function __construct(
        \StripeIntegration\Payments\Model\Stripe\Service\StripeObjectServicePool $stripeObjectServicePool,
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Helper\Order $orderHelper,
        \StripeIntegration\Payments\Helper\Webhooks $webhooksHelper,
        \Magento\Framework\Event\ManagerInterface $eventManager
    )
    {
        $stripeObjectService = $stripeObjectServicePool->getStripeObjectService('events');
        $this->setData($stripeObjectService);

        $this->eventManager = $eventManager;
        $this->webhooksHelper = $webhooksHelper;
        $this->helper = $helper;
        $this->orderHelper = $orderHelper;

    }
    public function process($arrEvent, $object)
    {
        if (empty($object['payment_intent']))
            return;

        $orders = $this->webhooksHelper->loadOrderFromEvent($arrEvent, true);

        foreach ($orders as $order)
        {
            $this->webhooksHelper->detectRaceCondition($order->getIncrementId(), ['charge.refunded']);
        }

        foreach ($orders as $order)
        {
            $this->eventManager->dispatch(
                'stripe_payments_review_closed_before',
                ['order' => $order, 'object' => $object]
            );

            if ($object['reason'] == "approved")
            {
                if ($order->canUnhold())
                    $order->unhold();

                $comment = __("The payment has been approved via Stripe.");
                $order->addStatusToHistory(false, $comment, $isCustomerNotified = false);
                $this->orderHelper->saveOrder($order);
            }
            else if ($object['reason'] == "refunded_as_fraud")
            {
                if ($order->canUnhold())
                    $order->unhold();

                $comment = __("The payment has been rejected as fraudulent via Stripe.");
                $order->setState($order::STATE_PAYMENT_REVIEW);
                $order->addStatusToHistory($order::STATUS_FRAUD, $comment, $isCustomerNotified = false);
                $this->orderHelper->saveOrder($order);
            }
            else
            {
                $comment = __("The payment was canceled through Stripe with reason: %1.", ucfirst(str_replace("_", " ", $object['reason'])));
                $order->addStatusToHistory(false, $comment, $isCustomerNotified = false);
                $this->orderHelper->saveOrder($order);
            }

            $this->eventManager->dispatch(
                'stripe_payments_review_closed_after',
                ['order' => $order, 'object' => $object]
            );
        }

    }
}