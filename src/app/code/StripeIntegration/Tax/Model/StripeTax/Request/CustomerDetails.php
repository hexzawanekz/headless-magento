<?php

namespace StripeIntegration\Tax\Model\StripeTax\Request;

class CustomerDetails
{
    public const ADDRESS_KEY = 'address';
    public const ADDRESS_SOURCE_KEY = 'address_source';
    public const IP_ADDRESS_KEY = 'ip_address';
    public const TAX_IDS_KEY = 'tax_ids';
    public const TAXABILITY_OVERRIDE_KEY = 'taxability_override';

    private $address;
    private $addressSource;
    private $ipAddress;
    private $taxIds;
    private $taxabilityOverride;

    private $customerDetailsHelper;

    public function __construct(
        \StripeIntegration\Tax\Helper\CustomerDetails $customerDetailsHelper
    )
    {
        $this->customerDetailsHelper = $customerDetailsHelper;
    }

    public function formData($address, $quote)
    {
        $addressForApi = $this->customerDetailsHelper->getAddressFromShippingAssignment($address);
        if (!$addressForApi && $address->getCustomerId()) {
            $addressForApi = $this->customerDetailsHelper->getAddressFromDefaultAddresses($address->getCustomerId(), $quote);
        }

        if ($addressForApi) {
            $this->setAddress($addressForApi['data']);
            $this->setAddressSource($addressForApi['source']);
        } else {
            $this->setIpAddress($this->customerDetailsHelper->getCurrentUserIp());
        }
    }

    public function formDataForInvoiceTax($order)
    {
        if ($order->getIsVirtual()) {
            $addressForApi = $this->customerDetailsHelper->getAddressFromOrderAddress($order->getBillingAddress());
        } else {
            $addressForApi = $this->customerDetailsHelper->getAddressFromOrderAddress($order->getShippingAddress());
        }

        $this->setAddress($addressForApi['data']);
        $this->setAddressSource($addressForApi['source']);
    }

    public function toArray()
    {
        $customerDetails = [];
        if ($this->getAddress()) {
            $customerDetails[self::ADDRESS_KEY] = $this->getAddress();
            $customerDetails[self::ADDRESS_SOURCE_KEY] = $this->getAddressSource();
        } else {
            $customerDetails[self::IP_ADDRESS_KEY] = $this->getIpAddress();
        }

        return $customerDetails;
    }

    public function getAddress()
    {
        return $this->address;
    }

    public function setAddress($address)
    {
        $this->address = $address;

        return $this;
    }

    public function getAddressSource()
    {
        return $this->addressSource;
    }

    public function setAddressSource($addressSource)
    {
        $this->addressSource = $addressSource;

        return $this;
    }

    public function getIpAddress()
    {
        return $this->ipAddress;
    }

    public function setIpAddress($ipAddress)
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getTaxIds()
    {
        return $this->taxIds;
    }

    public function setTaxIds($taxIds)
    {
        $this->taxIds = $taxIds;

        return $this;
    }

    public function getTaxabilityOverride()
    {
        return $this->taxabilityOverride;
    }

    public function setTaxabilityOverride($taxabilityOverride)
    {
        $this->taxabilityOverride = $taxabilityOverride;

        return $this;
    }
}