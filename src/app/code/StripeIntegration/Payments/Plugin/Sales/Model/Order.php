<?php

namespace StripeIntegration\Payments\Plugin\Sales\Model;

class Order
{
    private $orders = [];
    private $config;
    private $dataHelper;

    public function __construct(
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Helper\Data $dataHelper
    ) {
        $this->config = $config;
        $this->dataHelper = $dataHelper;
    }

    public function afterCanCancel($order, $result)
    {
        if (isset($this->orders[$order->getIncrementId()]))
            return $this->orders[$order->getIncrementId()];

        $method = $order->getPayment()->getMethod();

        if ($method != "stripe_payments_checkout")
            return $result;

        if (!$this->dataHelper->isAdmin())
            return $result;

        $checkoutSessionId = $order->getPayment()->getAdditionalInformation("checkout_session_id");
        if (empty($checkoutSessionId))
            return $result;

        $stripe = $this->config->getStripeClient();

        if (empty($stripe))
            return $result;

        try
        {
            $checkoutSession = $stripe->checkout->sessions->retrieve($checkoutSessionId, []);

            if ($checkoutSession->status == "open")
                $this->orders[$order->getIncrementId()] = false;
            else
                $this->orders[$order->getIncrementId()] = $result;
        }
        catch (\Exception $e)
        {
            $this->orders[$order->getIncrementId()] = $result;
        }

        return $this->orders[$order->getIncrementId()];
    }
}
