<?php
namespace StripeIntegration\Payments\Model;

use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Magento\Quote\Model\Quote;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Tax\Model\Sales\Total\Quote\CommonTaxCollector;
use StripeIntegration\Payments\Exception\LocalizedException;
use StripeIntegration\Payments\Model\SubscriptionProductFactory;
use Magento\Tax\Model\Config;

class InitialFee extends AbstractTotal
{
    public const INITIAL_FEE_TYPE = 'initial_fee';
    public const INITIAL_FEE_CODE = 'initial_fee';

    private $storeManager;
    private $initialFeeHelper;
    private $initialFeeHelperFactory;
    private $subscriptionProductFactory;
    private $taxConfig;
    private $count = 0;

    public function __construct(
        \StripeIntegration\Payments\Helper\InitialFeeFactory $initialFeeHelperFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        SubscriptionProductFactory $subscriptionProductFactory,
        Config $taxConfig
    )
    {
        $this->initialFeeHelperFactory = $initialFeeHelperFactory;
        $this->setCode('initial_fee');
        $this->storeManager = $storeManager;
        $this->subscriptionProductFactory = $subscriptionProductFactory;
        $this->taxConfig = $taxConfig;
    }

    /**
     * @param Quote $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param Total $total
     * @return $this
     */
    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ) {
        parent::collect($quote, $shippingAssignment, $total);

        $items = $shippingAssignment->getItems();
        if (!count($items))
            return $this;

        $address = $shippingAssignment->getShipping()->getAddress();
        $associatedTaxables = $address->getAssociatedTaxables();
        if (!$associatedTaxables) {
            $associatedTaxables = [];
        } else {
            // Remove existing initial_fee associated taxables
            foreach ($associatedTaxables as $key => $taxable) {
                if ($taxable[CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_TYPE] == self::INITIAL_FEE_TYPE) {
                    unset($associatedTaxables[$key]);
                }
            }
        }

        $mappedItems = [];
        $totalBaseInitialFee = 0;
        $totalInitialFee = 0;
        $subscriptionProcessed = false;
        $taxClassId = null;
        foreach ($items as $item) {
            $subscriptionProduct = $this->subscriptionProductFactory->create()->fromProductId($item->getProductId());
            if ($subscriptionProduct->isSubscriptionProduct()) {
                // If there is more than one subscription product in the cart, we will need to refactor the tax
                // calculation for the initial fee.
                if ($subscriptionProcessed) {
                    throw new LocalizedException(__('You can only purchase one subscription at a time.'));
                }

                $payment = $quote->getPayment();
                // If we are in the scenario of migrating subscriptions, the initial fee of the product will not
                // be taken into consideration.
                // Also, if there is a recurring subscription the initial fee will be disregarded.
                if ($payment &&
                    ($payment->getAdditionalInformation('remove_initial_fee') ||
                        $payment->getAdditionalInformation('is_recurring_subscription'))
                ) {
                    $baseInitialFee = 0;
                    $initialFee = 0;
                } else {
                    // Returns the initial fee amount in the base currency for 1 product
                    $baseInitialFee = $subscriptionProduct->getBaseInitialFeeAmount();
                    // Returns the initial fee amount in the current currency for 1 product
                    $initialFee = $subscriptionProduct->getInitialFeeAmount(1);
                }

                $taxClassId = $subscriptionProduct->getProduct()->getTaxClassId();
                $priceIncludesTax = $this->taxConfig->priceIncludesTax();
                $itemTaxableCode = self::INITIAL_FEE_CODE . $this->getNextIncrement();
                $qty = $this->getHelper()->getItemQty($item, $item->getProduct()->getId());

                $associatedTaxables[] = [
                    CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_TYPE => self::INITIAL_FEE_TYPE,
                    CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_CODE => $itemTaxableCode,
                    CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_UNIT_PRICE => $initialFee,
                    CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_BASE_UNIT_PRICE => $baseInitialFee,
                    CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_QUANTITY => $qty,
                    CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_TAX_CLASS_ID => $taxClassId,
                    CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_PRICE_INCLUDES_TAX => $priceIncludesTax,
                    CommonTaxCollector::KEY_ASSOCIATED_TAXABLE_ASSOCIATION_ITEM_CODE
                    => CommonTaxCollector::ASSOCIATION_ITEM_CODE_FOR_QUOTE
                ];

                // Save the mapped items so that after the tax is calculated we can set the values on the item
                $mappedItems[$itemTaxableCode] = $item;

                // Add the initial fee values to the total variables
                $totalInitialFee += $initialFee * $qty;
                $totalBaseInitialFee += $baseInitialFee * $qty;

                $subscriptionProcessed = true;
            }
        }
        $address->setAssociatedTaxables($associatedTaxables);
        $total->setInitialFeeMappedItems($mappedItems);

        $total->setInitialFeeAmount($totalInitialFee);
        $total->setBaseInitialFeeAmount($totalBaseInitialFee);
        // Because there can only be one subscription in the cart, we set the tax class id to use for creating the
        // Stripe tax request here.
        $total->setInitialFeeTaxClassId($taxClassId);

        return $this;
    }

    /**
     * @param Total $total
     */
    protected function clearValues(Total $total)
    {
        $total->setTotalAmount('initial_fee', 0);
        $total->setBaseTotalAmount('base_initial_fee', 0);
        $total->setInitialFeeAmount(0);
        $total->setBaseInitialFeeAmount(0);
        $total->setGrandTotal(0);
        $total->setBaseGrandTotal(0);

        // $total->setTotalAmount('tax', 0);
        // $total->setBaseTotalAmount('base_tax', 0);
        // $total->setTotalAmount('discount_tax_compensation', 0);
        // $total->setBaseTotalAmount('base_discount_tax_compensation', 0);
        // $total->setTotalAmount('shipping_discount_tax_compensation', 0);
        // $total->setBaseTotalAmount('base_shipping_discount_tax_compensation', 0);
        // $total->setSubtotalInclTax(0);
        // $total->setBaseSubtotalInclTax(0);
    }

    /**
     * @param Quote $quote
     * @param Total $total
     * @return array
     */
    public function fetch(Quote $quote, Total $total)
    {
        if ($quote->getIsMultiShipping())
            $baseAmount = $total->getInitialFeeAmount();
        else
            $baseAmount = $this->getHelper()->getTotalInitialFeeFor($quote->getAllItems(), $quote, 1);

        $store = $this->storeManager->getStore();

        if (!$store || !$store->getCurrentCurrencyCode() || !$store->getBaseCurrency())
            return null;

        $amount = $store->getBaseCurrency()->convert($baseAmount, $store->getCurrentCurrencyCode());

        if ($baseAmount)
        {
            return [
                'code' => $this->getCode(),
                'title' => 'Initial Fee',
                'base_value' => $baseAmount,
                'value' => $amount
            ];
        }

        return null;
    }

    /**
     * @return \Magento\Framework\Phrase
     */
    public function getLabel()
    {
        return __('Initial Fee');
    }

    private function getHelper()
    {
        if (empty($this->initialFeeHelper))
            $this->initialFeeHelper = $this->initialFeeHelperFactory->create();

        return $this->initialFeeHelper;
    }

    private function getNextIncrement()
    {
        return ++$this->count;
    }
}
