<?php

namespace StripeIntegration\Tax\Plugin\Quote\Model\Quote\Address;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Quote\Model\Quote\Address\ToOrder as QuoteAddressToOrder;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use StripeIntegration\Tax\Model\Order\StripeDataManagement;

class ToOrder
{
    private $stripeDataManagement;

    public function __construct(StripeDataManagement $stripeDataManagement)
    {
        $this->stripeDataManagement = $stripeDataManagement;
    }

    public function afterConvert(
        QuoteAddressToOrder $subject,
        OrderInterface $result,
        QuoteAddress $quoteAddress,
        $data = []
    )
    {
        return $this->stripeDataManagement->setFromAddressData($result, $quoteAddress);
    }
}