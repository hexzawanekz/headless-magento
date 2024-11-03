<?php

namespace StripeIntegration\Payments\Plugin\QuoteGraphQl\Model\Resolver;

class PlaceOrder
{
    private $orderHelper;

    public function __construct(
        \StripeIntegration\Payments\Helper\Order $orderHelper
    ) {
        $this->orderHelper = $orderHelper;
    }

    public function afterResolve(
        \Magento\QuoteGraphQl\Model\Resolver\PlaceOrder $subject,
        $result,
        \Magento\Framework\GraphQl\Config\Element\Field $field,
        $context,
        \Magento\Framework\GraphQl\Schema\Type\ResolveInfo $info,
        array $value = null,
        array $args = null
    )
    {
        if (!empty($result["order"]["order_number"]))
        {
            $order = $this->orderHelper->loadOrderByIncrementId($result["order"]["order_number"]);
            $payment = $order->getPayment();

            if ($payment->getMethod() == "stripe_payments" && $payment->getAdditionalInformation("client_secret"))
            {
                $result["order"]["client_secret"] = $payment->getAdditionalInformation("client_secret");
            }
        }

        return $result;
    }
}
