<?php

namespace StripeIntegration\Tax\Model\AdditionalFees;

use Magento\Framework\DataObject;

/**
 * Generic object for adding additional fees from a 3rd party developer. The object will be provided as a parameter
 * for an observer.
 *
 * Will be extended for specific types of additional fees,
 * like item fees or fees that are applied to the whole quote/order etc.
 */
class AdditionalFees extends DataObject
{
    /**
     * Method for adding a new fee to the fees array contained within the object. The reason an array of details is used
     * here is that there might be cases where there will be more than one additional fee.
     *
     * @param array $additionalFee
     * @return void
     */
    public function addAdditionalFee(array $additionalFee)
    {
        if (!$this->getAdditionalFees()) {
            $this->setAdditionalFees([]);
        }

        $additionalFees = $this->getAdditionalFees();
        $additionalFees[] = $additionalFee;

        $this->setAdditionalFees($additionalFees);
    }

    /**
     * Method will be used to clear tge object before setting it as an observer parameter
     *
     * @return $this
     */
    public function clearValues()
    {
        $this->setAdditionalFees([]);

        return $this;
    }
}