<?php

namespace StripeIntegration\Payments\Helper;

use StripeIntegration\Payments\Exception\GenericException;

class Creditmemo
{
    private $creditmemoRepository;
    private $creditmemoManagement;

    public function __construct(
        \Magento\Sales\Api\CreditmemoRepositoryInterface $creditmemoRepository,
        \Magento\Sales\Api\CreditmemoManagementInterface $creditmemoManagement
    ) {
        $this->creditmemoRepository = $creditmemoRepository;
        $this->creditmemoManagement = $creditmemoManagement;
    }

    public function saveCreditmemo($creditmemo)
    {
        return $this->creditmemoRepository->save($creditmemo);
    }

    public function refundCreditmemo($creditmemo)
    {
        $this->creditmemoManagement->refund($creditmemo);
    }

    public function sendEmail($creditmemoId)
    {
        $this->creditmemoManagement->notify($creditmemoId);
    }

    public function validateBaseRefundAmount($order, $baseAmount)
    {
        if (!$order->canCreditmemo())
        {
            throw new GenericException("The order cannot be refunded");
        }

        if ($baseAmount <= 0)
        {
            throw new GenericException("Cannot refund an amount of $baseAmount.");
        }
    }
}
