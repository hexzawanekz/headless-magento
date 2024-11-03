<?php

declare(strict_types=1);

namespace StripeIntegration\Payments\Helper;

class Discount
{
    private $couponCollection;

    public function __construct(
        \StripeIntegration\Payments\Model\ResourceModel\Coupon\Collection $couponCollection
    )
    {
        $this->couponCollection = $couponCollection;
    }

    public function getDiscountRules(?string $appliedRuleIds): array
    {
        $foundRules = [];

        if (empty($appliedRuleIds))
            return $foundRules;

        $appliedRuleIds = explode(",", $appliedRuleIds);

        foreach ($appliedRuleIds as $ruleId)
        {
            $discountRule = $this->couponCollection->getByRuleId($ruleId);
            if ($discountRule)
                $foundRules[] = $discountRule;
        }

        return $foundRules;
    }
}