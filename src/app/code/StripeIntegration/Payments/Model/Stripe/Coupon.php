<?php

namespace StripeIntegration\Payments\Model\Stripe;

use Magento\Framework\Exception\LocalizedException;

class Coupon
{
    use StripeObjectTrait;

    private $objectSpace = 'coupons';
    public $rule = null;
    private $helper;

    public function __construct(
        \StripeIntegration\Payments\Model\Stripe\Service\StripeObjectServicePool $stripeObjectServicePool,
        \StripeIntegration\Payments\Helper\Generic $helper
    )
    {
        $stripeObjectService = $stripeObjectServicePool->getStripeObjectService($this->objectSpace);
        $this->setData($stripeObjectService);

        $this->helper = $helper;
    }

    public function fromSubscriptionProfile($profile)
    {
        $currency = $profile['currency'];
        $amount = $profile['discount_amount_magento'];
        $data = $this->getCouponParams($amount, $currency, $profile);

        if (!$data)
            return $this;

        $this->getObject($data['id']);

        if (!$this->getStripeObject())
            $this->createObject($data);

        if (!$this->getStripeObject())
            throw new \Magento\Framework\Exception\LocalizedException(
                __("The discount could not be created in Stripe: %1", $this->getLastError())
            );

        return $this;
    }
    public function fromGiftCards($order)
    {
        $currency = $order->getOrderCurrencyCode();
        $amount = $order->getGiftCardsAmount();

        $discountType = "amount_off";
        $stripeAmount = $this->helper->convertMagentoAmountToStripeAmount($amount, $currency);

        $giftCards = json_decode($order->getGiftCards());
        if (count($giftCards) > 1)
        {
            $name = __("%1 Gift Cards", $this->helper->addCurrencySymbol($amount, $currency));
        }
        else
        {
            $name = __("%1 Gift Card", $this->helper->addCurrencySymbol($amount, $currency));
        }

        $params = [
            $discountType => $stripeAmount,
            'currency' => $currency,
            'name' => $name
        ];

        try
        {
            $this->createObject($params);
        }
        catch (\Exception $e)
        {
            throw new \Magento\Framework\Exception\LocalizedException(__("The gift cards for order #%1 could not be created in Stripe: %2", $order->getIncrementId(), $e->getMessage()));
        }

        return $this;
    }

    private function getCouponExpirationParams($ruleId, $hasTrial)
    {
        $defaults = ['duration' => 'forever'];

        if (empty($ruleId))
            return $defaults;

        $coupon = $this->helper->loadStripeCouponByRuleId($ruleId);
        $duration = $coupon->duration();
        $months = $coupon->months();

        if ($months && $months > 0)
        {
            return [
                'duration' => $duration,
                'duration_in_months' => $months
            ];
        }
        else if ($duration == "once" && $hasTrial)
        {
            return [
                'duration' => 'repeating',
                'duration_in_months' => 1
            ];
        }

        return ['duration' => $duration];
    }

    private function getCouponParams($amount, $currency, $profile)
    {
        if (empty($amount) || empty($profile['expiring_coupon']['rule_id']))
            return null;

        $ruleId = $profile['expiring_coupon']['rule_id'];
        $this->rule = $rule = $this->helper->loadRuleByRuleId($ruleId);
        $action = $rule->getSimpleAction();
        if (empty($action))
            return null;

        $action = "by_fixed";
        $discountType = "amount_off";
        $stripeAmount = $this->helper->convertMagentoAmountToStripeAmount($amount, $currency);
        $couponId = ((string)$stripeAmount) . strtoupper($currency);
        $name = $this->helper->addCurrencySymbol($amount, $currency) . " Discount";

        $hasTrial = $profile['trial_end'] || $profile['trial_days'];
        $expirationParams = $this->getCouponExpirationParams($ruleId, $hasTrial);

        switch ($expirationParams['duration'])
        {
            case 'repeating':
                $couponId .= "-months-" . $expirationParams['duration_in_months'];
                break;
            case 'once':
                $couponId .= "-once";
                break;
        }

        $params = [
            'id' => $couponId,
            $discountType => $stripeAmount,
            'currency' => $currency,
            'name' => $name
        ];

        $params = array_merge($params, $expirationParams);

        return $params;
    }

    public function getApplyToShipping()
    {
        if (!empty($this->rule))
        {
            return $this->rule->getApplyToShipping();
        }

        return false;
    }
}
