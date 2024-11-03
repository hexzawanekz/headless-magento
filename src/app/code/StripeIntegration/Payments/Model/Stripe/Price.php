<?php

namespace StripeIntegration\Payments\Model\Stripe;

class Price
{
    use StripeObjectTrait;

    private $objectSpace = 'prices';
    private $subscriptionsHelper;
    private $helper;

    public function __construct(
        \StripeIntegration\Payments\Model\Stripe\Service\StripeObjectServicePool $stripeObjectServicePool,
        \StripeIntegration\Payments\Helper\Subscriptions $subscriptionsHelper,
        \StripeIntegration\Payments\Helper\Generic $helper
    )
    {
        $stripeObjectService = $stripeObjectServicePool->getStripeObjectService($this->objectSpace);
        $this->setData($stripeObjectService);

        $this->subscriptionsHelper = $subscriptionsHelper;
        $this->helper = $helper;
    }

    public function generateNickname($stripeAmount, $currency, $interval, $intervalCount)
    {
        if (!empty($interval) && !empty($intervalCount))
        {
            return $this->subscriptionsHelper->formatInterval($stripeAmount, $currency, $intervalCount, $interval);
        }

        return $this->helper->formatStripePrice($stripeAmount, $currency);
    }

    public function generateId($stripeProductId, $stripeUnitAmount, $currency, $interval, $intervalCount)
    {
        $id = "{$stripeUnitAmount}{$currency}";

        if ($interval && $intervalCount)
        {
            $id .= "-{$interval}-{$intervalCount}";
        }

        $id .= "-{$stripeProductId}";

        return $id;
    }

    private function formatCreationData($stripeProductId, $stripeUnitAmount, $currency, $interval, $intervalCount)
    {
        $data = [
            'currency' => strtoupper($currency),
            'unit_amount' => $stripeUnitAmount,
            'product' => $stripeProductId
        ];

        if (!empty($interval) && !empty($intervalCount))
        {
            $data['recurring'] = [
                'interval' => $interval,
                'interval_count' => $intervalCount
            ];
        }

        $data['nickname'] = $this->generateNickname($stripeUnitAmount, $currency, $interval, $intervalCount);

        return $data;
    }

    public function fromData($stripeProductId, $stripeUnitAmount, $currency, $interval = null, $intervalCount = null)
    {
        $data = $this->formatCreationData($stripeProductId, $stripeUnitAmount, $currency, $interval, $intervalCount);
        $priceId = $this->generateId($stripeProductId, $stripeUnitAmount, $currency, $interval, $intervalCount);

        if (!$this->lookupSingle($priceId))
        {
            $data['lookup_key'] = $priceId;

            try
            {
                $this->createObject($data);
            }
            catch (\Exception $e)
            {
                throw new \Magento\Framework\Exception\LocalizedException(__("The price could not be created in Stripe: %1", $e->getMessage()));
            }
        }

        return $this;
    }

    public function fromOrderItem($item, $order, $stripeProduct)
    {
        $stripeProductId = $stripeProduct->id;
        $stripeUnitAmount = $this->helper->convertMagentoAmountToStripeAmount($item->getPrice(), $order->getOrderCurrencyCode());
        $currency = strtoupper($order->getOrderCurrencyCode());
        $interval = null;
        $intervalCount = null;

        $data = $this->formatCreationData($stripeProductId, $stripeUnitAmount, $currency, $interval, $intervalCount);
        $priceId = $this->generateId($stripeProductId, $stripeUnitAmount, $currency, $interval, $intervalCount);

        if (!$this->lookupSingle($priceId))
        {
            $data['lookup_key'] = $priceId;

            try
            {
                $this->createObject($data);
            }
            catch (\Exception $e)
            {
                throw new \Magento\Framework\Exception\LocalizedException(__("The price for product \"%1\" could not be created in Stripe: %2", $item->getName(), $e->getMessage()));
            }
        }

        return $this;
    }
}
