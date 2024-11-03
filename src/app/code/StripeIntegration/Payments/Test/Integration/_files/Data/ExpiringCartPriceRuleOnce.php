<?php

use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();

$rule = $objectManager->create(\Magento\SalesRule\Model\Rule::class);

// Define the rule data
$rule->setName('50% Off Cart Price Rule')
    ->setDescription('50% discount on the entire cart.')
    ->setIsActive(1)
    ->setStopRulesProcessing(0)
    ->setIsAdvanced(1)
    ->setProductIds('')
    ->setSortOrder(1)
    ->setSimpleAction(\Magento\SalesRule\Model\Rule::BY_PERCENT_ACTION)
    ->setDiscountAmount(50)  // 50% discount
    ->setDiscountQty(null)
    ->setDiscountStep(0)
    ->setSimpleFreeShipping('0')
    ->setApplyToShipping('0')
    ->setIsRss(0)
    ->setWebsiteIds([1])    // Assuming you're working with the main website. Adjust if necessary.
    ->setCustomerGroupIds([0, 1, 2, 3])  // Applying to all customer groups. Adjust if necessary.
    ->setCouponType(\Magento\SalesRule\Model\Rule::COUPON_TYPE_NO_COUPON);

// Save the rule
$rule->save();


// Expiring coupons should be ignored when a cart price rule has no discount coupon, so we add a temporary entry to test this
$couponModel = $objectManager->create(\StripeIntegration\Payments\Model\Coupon::class);
$couponModel->setRuleId($rule->getId());
$couponModel->setCouponDuration($couponModel::COUPON_ONCE);
$couponModel->save();