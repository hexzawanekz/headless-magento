<?php

namespace StripeIntegration\Payments\Plugin\Sales\Model;

class Invoice
{
    private $transactions = [];
    private $transactionSearchResultFactory;
    private $dataHelper;
    private $subscriptionProductFactory;

    public function __construct(
        \Magento\Sales\Api\Data\TransactionSearchResultInterfaceFactory $transactionSearchResultFactory,
        \StripeIntegration\Payments\Helper\Data $dataHelper,
        \StripeIntegration\Payments\Model\SubscriptionProductFactory $subscriptionProductFactory
    )
    {
        $this->transactionSearchResultFactory = $transactionSearchResultFactory;
        $this->dataHelper = $dataHelper;
        $this->subscriptionProductFactory = $subscriptionProductFactory;
    }

    public function getTransactions($order)
    {
        if (isset($this->transactions[$order->getId()]))
            return $this->transactions[$order->getId()];

        $transactions = $this->transactionSearchResultFactory->create()->addOrderIdFilter($order->getId());
        return $this->transactions[$order->getId()] = $transactions;
    }

    public function aroundCanCancel($subject, \Closure $proceed)
    {
        $order = $subject->getOrder();

        $isStripePaymentMethod = (strpos($order->getPayment()->getMethod(), "stripe_") === 0);

        if (!$isStripePaymentMethod || !$this->dataHelper->isAdmin())
            return $proceed();

        $isPending = ($subject->getState() == \Magento\Sales\Model\Order\Invoice::STATE_OPEN);
        $transactions = $this->getTransactions($order);
        $hasTransactions = ($transactions->getSize() > 0);
        $wasCaptured = false;
        foreach ($transactions->getItems() as $transaction)
        {
            if ($transaction->getTxnType() == "capture")
                $wasCaptured = true;
        }

        if ($isPending && $hasTransactions)
            return false;

        if ($wasCaptured)
            return false;

        return $proceed();
    }

    public function hasSubscriptions($subject)
    {
        $items = $subject->getAllItems();

        foreach ($items as $item)
        {
            if (!$item->getProductId())
                continue;

            if ($this->subscriptionProductFactory->create()->fromProductId($item->getProductId())->isSubscriptionProduct())
                return true;
        }

        return false;
    }
}
