<?php

namespace StripeIntegration\Tax\Helper;

use Magento\Customer\Api\AccountManagementInterface as CustomerAccountManagement;
use Magento\Directory\Model\RegionFactory;
use Magento\Directory\Model\ResourceModel\Region\Collection;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;

class CustomerDetails
{
    private $remoteAddress;
    private $customerAccountManagement;
    private $regionCollection;

    public function __construct(
        RemoteAddress $remoteAddress,
        CustomerAccountManagement $customerAccountManagement,
        Collection $regionCollection
    ) {
        $this->remoteAddress = $remoteAddress;
        $this->customerAccountManagement = $customerAccountManagement;
        $this->regionCollection = $regionCollection;
    }

    public function getCurrentUserIp()
    {
        return $this->remoteAddress->getRemoteAddress();
    }

    public function getAddressFromShippingAssignment($address)
    {
        return $this->getDetailsFromAddress($address, $address->getAddressType());
    }

    public function getAddressFromOrderAddress($address)
    {
        return $this->getDetailsFromAddress($address, $address->getAddressType());
    }

    public function getAddressFromDefaultAddresses($customerId, $quote)
    {
        if ($this->isQuoteVirtual($quote)) {
            $defaultBillingAddress = $this->customerAccountManagement->getDefaultBillingAddress($customerId);
            $addressDetails = $this->getDetailsFromAddress($defaultBillingAddress, 'billing');
        } else {
            $defaultShippingAddress = $this->customerAccountManagement->getDefaultShippingAddress($customerId);
            $addressDetails = $this->getDetailsFromAddress($defaultShippingAddress, 'shipping');
            if (!$addressDetails) {
                $defaultBillingAddress = $this->customerAccountManagement->getDefaultBillingAddress($customerId);
                $addressDetails = $this->getDetailsFromAddress($defaultBillingAddress, 'billing');
            }
        }

        if (!$addressDetails) {
            return null;
        }

        return $addressDetails;
    }

    private function isQuoteVirtual($quote)
    {
        return $quote->getIsVirtual() || $quote->getItemsQty() == $quote->getVirtualItemsQty();
    }

    private function getDetailsFromAddress($address, $source)
    {
        if ($address && $address->getCountryId()) {
            // If the country is US and we don't have a postcode, return null so that the IP address is selected
            // further down the line
            if ($address->getCountryId() == 'US' && !$address->getPostcode()) {
                return null;
            }

            return [
                'data' => [
                    'country' => $address->getCountryId(),
                    'postal_code' => $address->getPostcode(),
                    'state' => $this->getRegionCode($address->getRegionId()) ?: $address->getRegionId(),
                    'city' => $address->getCity(),
                    'line1' => $address->getStreet()[0]
                ],
                'source' => $source
            ];
        }

        return null;
    }

    private function getRegionCode($regionId)
    {
        $region = $this->regionCollection->getItemById($regionId);

        if ($region) {
            return $region->getCode();
        }

        return null;
    }
}