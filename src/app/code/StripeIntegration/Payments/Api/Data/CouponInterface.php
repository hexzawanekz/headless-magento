<?php
namespace StripeIntegration\Payments\Api\Data;

use Magento\Framework\Api\ExtensibleDataInterface;

interface CouponInterface extends ExtensibleDataInterface
{
    public const RULE_NAME = 'subscription';
    public const EXTENSION_CODE = 'coupon';

    public const COUPON_ID = 'id';
    public const COUPON_RULE_ID = 'rule_id';
    public const COUPON_DURATION = 'coupon_duration';
    public const COUPON_MONTHS = 'coupon_months';

    /**
     * Get Coupon ID
     *
     * @return int
     */
    public function getCouponId();

    /**
     * Set Coupon ID
     *
     * @param int $id
     * @return $this
     */
    public function setCouponId($id);

    /**
     * Get Sales Rule ID
     *
     * @return int
     */
    public function getCouponSalesRuleId();

    /**
     * Set Sales Rule ID
     *
     * @param int $ruleId
     * @return $this
     */
    public function setCouponSalesRuleId($ruleId);

    /**
     * Get Coupon Duration
     *
     * @return string
     */
    public function getCouponDuration();

    /**
     * Set Coupon Duration
     *
     * @param string $couponDuration
     * @return $this
     */
    public function setCouponDuration($couponDuration);

    /**
     * Get Coupon Months
     *
     * @return string
     */
    public function getCouponMonths();

    /**
     * Set Coupon Months
     *
     * @param string $couponMonths
     * @return $this
     */
    public function setCouponMonths($couponMonths);
}
