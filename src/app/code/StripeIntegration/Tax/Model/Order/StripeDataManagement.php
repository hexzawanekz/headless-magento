<?php
namespace StripeIntegration\Tax\Model\Order;

use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Sales\Model\Order;

/**
 * Data transferring between quote address and order
 */
class StripeDataManagement
{
    private $stripeTaxCalculationId;

    public function __construct()
    {
        $this->stripeTaxCalculationId = [];
    }

    public function setFromAddressData(Order $order, QuoteAddress $address)
    {
        if ($address->getData('stripe_tax_calculation_id')) {
            $this->stripeTaxCalculationId[$order->getIncrementId()] = $address->getData('stripe_tax_calculation_id');
        }

        return $order;
    }

    public function setDataFrom(Order $order)
    {
        if (isset($this->stripeTaxCalculationId[$order->getIncrementId()])) {
            $order->setData('stripe_tax_calculation_id', $this->stripeTaxCalculationId[$order->getIncrementId()]);
        }

        return $order;
    }
}
