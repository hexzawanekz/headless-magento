<?php
namespace StripeIntegration\Payments\Plugin\Quote;

use StripeIntegration\Payments\Model\Order\InitialFeeManagement;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Quote\Model\Quote\Address\ToOrder as QuoteAddressToOrder;
use Magento\Quote\Model\Quote\Address as QuoteAddress;

class InitialFeeToOrder
{
    /**
     * @var InitialFeeManagement
     */
    private $extensionManagement;

    public function __construct(InitialFeeManagement $extensionManagement)
    {
        $this->extensionManagement = $extensionManagement;
    }

    public function afterConvert(
        QuoteAddressToOrder $subject,
        OrderInterface $result,
        QuoteAddress $quoteAddress,
        $data = []
    ) {
        return $this->extensionManagement->setFromAddressData($result, $quoteAddress);
    }
}
