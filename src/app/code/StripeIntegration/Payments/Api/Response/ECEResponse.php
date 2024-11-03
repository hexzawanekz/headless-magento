<?php

namespace StripeIntegration\Payments\Api\Response;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Model\Order\Shipment;

class ECEResponse
{
    // Constructor dependencies
    private $serializer;
    private $directoryHelper;
    private $scopeConfig;
    private $estimateAddressFactory;
    private $shippingConfig;
    private $priceCurrency;
    private $taxHelper;
    private $shipmentEstimation;
    private $taxCalculation;
    private $allowedCountries;
    private $region;
    private $shippingInformationFactory;
    private $shippingInformationManagement;
    private $config;
    private $initParams;
    private $helper;
    private $addressHelper;
    private $quoteHelper;
    private $productHelper;

    // Local data
    private $resolvePayload = [];
    private $elementOptions = [];
    private $quote;
    private $storeId;
    private $location;

    public function __construct(
        \Magento\Framework\Serialize\SerializerInterface $serializer,
        \Magento\Directory\Helper\Data $directoryHelper,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Api\Data\EstimateAddressInterfaceFactory $estimateAddressFactory,
        \Magento\Shipping\Model\Config $shippingConfig,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        \Magento\Tax\Helper\Data $taxHelper,
        \Magento\Quote\Api\ShipmentEstimationInterface $shipmentEstimation,
        \Magento\Tax\Api\TaxCalculationInterface $taxCalculation,
        \Magento\Directory\Model\AllowedCountries $allowedCountries,
        \Magento\Directory\Model\Region $region,
        \Magento\Checkout\Api\Data\ShippingInformationInterfaceFactory $shippingInformationFactory,
        \Magento\Checkout\Api\ShippingInformationManagementInterface $shippingInformationManagement,
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Helper\InitParams $initParams,
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Helper\Address $addressHelper,
        \StripeIntegration\Payments\Helper\Quote $quoteHelper,
        \StripeIntegration\Payments\Helper\Product $productHelper,
        $location = null
    )
    {
        $this->serializer = $serializer;
        $this->directoryHelper = $directoryHelper;
        $this->scopeConfig = $scopeConfig;
        $this->estimateAddressFactory = $estimateAddressFactory;
        $this->shippingConfig = $shippingConfig;
        $this->priceCurrency = $priceCurrency;
        $this->taxHelper = $taxHelper;
        $this->shipmentEstimation = $shipmentEstimation;
        $this->taxCalculation = $taxCalculation;
        $this->allowedCountries = $allowedCountries;
        $this->region = $region;
        $this->shippingInformationFactory = $shippingInformationFactory;
        $this->shippingInformationManagement = $shippingInformationManagement;

        $this->config = $config;
        $this->initParams = $initParams;
        $this->helper = $helper;
        $this->addressHelper = $addressHelper;
        $this->quoteHelper = $quoteHelper;
        $this->productHelper = $productHelper;

        // Local data
        $this->quote = $this->quoteHelper->getQuote();
        $this->storeId = $this->helper->getStoreId();
        $this->location = $location;
    }

    public function fromClickAt($location, $productId = null, $attribute = null)
    {
        switch ($location)
        {
            case 'checkout':
            case 'cart':
            case 'minicart':
                $this->resolvePayload = $this->getClickResolvePayload($location);
                $this->elementOptions = $this->initParams->getExpressCheckoutElementsOptions($this->resolvePayload);
                break;
            default: // Product page
                if (is_numeric($productId))
                {
                    $this->resolvePayload = $this->getProductResolvePayload($productId, $attribute);
                    $this->elementOptions = $this->initParams->getExpressCheckoutElementsOptions($this->resolvePayload, $productId);
                    $this->resolvePayload['lineItems'] = []; // This should be unset after getElementOptions(), because we still need the elementOptions['amount'] value, otherwise ECE wont display
                }
                else
                {
                    throw new CouldNotSaveException(__("Invalid product ID"));
                }
                break;
        }

        if (empty($this->resolvePayload))
            return $this;

        return $this;
    }

    public function fromNewShippingAddress($newAddress)
    {
        $this->quote = $this->quoteHelper->getQuote();
        $shippingAddress = $this->quote->getShippingAddress();
        $newData = $this->addressHelper->getPartialMagentoAddressFromECEAddress($newAddress, __("shipping"));
        $shippingAddress->addData($newData);

        // Save the quote and shipping address and collect new shipping rates
        $shippingAddress->setCollectShippingRates(true);

        $shippingRates = $this->getShippingRatesForQuoteShippingAddress();
        if (count($shippingRates) > 0)
        {
            // Set it on the quote
            $shippingAddress->setShippingMethod($shippingRates[0]['id']);
        }
        else
        {
            // Unset any existing shipping method from the quote
            $shippingAddress->setShippingMethod(null);
        }

        $this->quoteHelper->reCollectTotals($this->quote);
        $this->quoteHelper->saveQuote($this->quote);

        $this->resolvePayload = $this->getShippingResolvePayload();

        return $this;
    }

    public function fromNewShippingRate($shippingAddressData, $shippingMethodId)
    {
        $quote = $this->quote = $this->quoteHelper->getQuote();

        $newData = $this->addressHelper->getPartialMagentoAddressFromECEAddress($shippingAddressData, __("shipping"));

        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->addData($newData);

        if ($shippingMethodId) {
            // Set Shipping Method
            $shippingAddress->setShippingMethod($shippingMethodId)
                        ->setCollectShippingRates(true)
                        ->collectShippingRates();

            $parts = explode('_', $shippingMethodId);
            $carrierCode = array_shift($parts);
            $methodCode = implode("_", $parts);

            /** @var \Magento\Checkout\Api\Data\ShippingInformationInterface $shippingInformation */
            $shippingInformation = $this->shippingInformationFactory->create();
            $shippingInformation
                // ->setBillingAddress($shippingAddress)
                ->setShippingAddress($shippingAddress)
                ->setShippingCarrierCode($carrierCode)
                ->setShippingMethodCode($methodCode);

            $this->shippingInformationManagement->saveAddressInformation($quote->getId(), $shippingInformation);

            // Update totals
            $this->quoteHelper->reCollectTotals($quote);
        }

        $this->resolvePayload = $this->getShippingResolvePayload();

        return $this;
    }

    public function getData()
    {
        return [
            "resolvePayload" => $this->resolvePayload,
            "elementOptions" => $this->elementOptions
        ];
    }

    public function serialize()
    {
        return $this->serializer->serialize($this->getData());
    }

    public function quoteHasCompleteShippingAddress()
    {
        $shippingAddress = $this->quote->getShippingAddress();

        $address = $this->addressHelper->getStripeAddressFromMagentoAddress($shippingAddress);
        if (!empty($address["address"]["line1"])
            && !empty($address["address"]["city"])
            && !empty($address["address"]["country"])
            && !empty($address["address"]["postal_code"])
        )
        {
            return true;
        }

        return false;
    }

    public function quoteHasCompleteBillingAddress()
    {
        $billingAddress = $this->quote->getBillingAddress();

        $address = $this->addressHelper->getStripeAddressFromMagentoAddress($billingAddress);
        if (!empty($address["address"]["line1"])
            && !empty($address["address"]["city"])
            && !empty($address["address"]["country"])
            && !empty($address["address"]["postal_code"])
        )
        {
            return true;
        }

        return false;
    }

    protected function getClickResolvePayload($location = null)
    {
        $quoteHasItems = count($this->quote->getAllVisibleItems()) > 0;
        $requestShipping = ($quoteHasItems && !$this->quote->isVirtual());

        if ($location == "checkout" && $this->quoteHasCompleteShippingAddress())
        {
            $requestShipping = false;
        }

        $params = [
            'allowedShippingCountries' => $this->getAllowedShippingCountries(),
            'billingAddressRequired' => true, // Always required for Wallet Button
            'emailRequired' => true,
            'lineItems' => $this->getLineItems(),
            'phoneNumberRequired' => true,
            'shippingAddressRequired' => $requestShipping
        ];

        if ($requestShipping)
        {
            // The shipping address was not yet specified, or the quote is empty
            $params['shippingRates'] = $this->getShippingAddressRequiredRate();
        }

        return $params;
    }

    public function getShippingResolvePayload()
    {
        $params = [
            'lineItems' => $this->getLineItems()
        ];

        if ($this->location == "checkout")
        {
            // This scenario should only hit with OneStepCheckout modules where the address was
            // not completed before the Wallet Button was clicked. Because if it was completed,
            // there would be no "shippingaddresschanged" event.
            $shippingRates = $this->getShippingRatesForQuoteShippingAddress();

            if (!empty($shippingRates))
            {
                $params['shippingRates'] = $shippingRates;
            }
            else
            {
                // Not passing any shipping rates will cause the event to be rejected
            }

            return $params;
        }
        else
        {
            if ($this->quote->isVirtual())
            {
                $params['shippingRates'] = $this->getFreeDeliveryRate();

                return $params;
            }
            else
            {
                $shippingRates = $this->getShippingRatesForQuoteShippingAddress();

                if (!empty($shippingRates))
                {
                    $params['shippingRates'] = $shippingRates;
                }
                else
                {
                    // Not passing any shipping rates will cause the event to be rejected
                }
            }
        }

        return $params;
    }


    /**
     * Get Express Checkout initialization params for Single Product
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getProductResolvePayload($productId, $attribute)
    {
        try
        {
            /** @var \Magento\Catalog\Model\Product $product */
            $product = $this->productHelper->getProduct($productId);
        }
        catch (\Exception $e)
        {
            return [];
        }

        $currency = $this->getCurrencyFromQuote();

        // Get Current Items in Cart
        $items = $this->getLineItems();
        $amount = 0;
        foreach ($items as $item)
        {
            $amount += $item['amount'];
        }

        if (!$this->quoteHelper->isProductInCart($productId))
        {
            $shouldInclTax = $this->shouldCartPriceInclTax();
            $productPrice = $this->productHelper->getPrice($product);
            $convertedFinalPrice = $this->priceCurrency->convertAndRound(
                $productPrice,
                null,
                $currency
            );

            $price = $this->getProductDataPrice(
                $product,
                $convertedFinalPrice,
                $shouldInclTax,
                $this->quote->getCustomerId(),
                $this->quote->getStore()->getStoreId()
            );

            // Append Current Product
            $productTotal = $this->helper->convertMagentoAmountToStripeAmount($price, $currency);
            $amount += $productTotal;

            $items[] = [
                'name' => $product->getName(),
                'amount' => $productTotal
            ];
        }

        $params = [
            'allowedShippingCountries' => $this->getAllowedShippingCountries(),
            'billingAddressRequired' => true, // Always required for Wallet Button
            'emailRequired' => true,
            'lineItems' => $items,
            'phoneNumberRequired' => true,
            'shippingAddressRequired' => true
        ];

        $quoteHasItems = count($this->quote->getAllVisibleItems()) > 0;
        $requestShipping = ($quoteHasItems && !$this->quote->isVirtual()) || $this->productHelper->requiresShipping($product);

        if ($requestShipping)
        {
            // The shipping address was not yet specified, or the quote is empty
            $params['shippingRates'] = $this->getShippingAddressRequiredRate();
        }
        else
        {
            // Case of virtual products / carts. We use the shipping address to calculate taxes
            $params['shippingRates'] = $this->getFreeDeliveryRate();
        }

        return $params;
    }

    protected function getShippingRatesForQuoteShippingAddress()
    {
        $quote = $this->quote;
        $rates = [];

        if ($quote->isVirtual())
        {
            return [];
        }

        $rates = $this->shipmentEstimation->estimateByExtendedAddress($quote->getId(), $quote->getShippingAddress());

        if (empty($rates))
        {
            return [];
        }

        $shouldInclTax = $this->shouldCartPriceInclTax();
        $currency = $quote->getQuoteCurrencyCode();
        $result = [];
        foreach ($rates as $rate) {
            if ($rate->getErrorMessage()) {
                continue;
            }

            $result[] = [
                'id' => $rate->getCarrierCode() . '_' . $rate->getMethodCode(),
                'displayName' => implode(' - ', [$rate->getCarrierTitle(), $rate->getMethodTitle()]),
                //'detail' => $rate->getMethodTitle(),
                'amount' => $this->helper->convertMagentoAmountToStripeAmount($shouldInclTax ? $rate->getPriceInclTax() : $rate->getPriceExclTax(), $currency)
            ];
        }

        return $result;
    }

    protected function getFreeDeliveryRate()
    {
        $shippingRates[] = [
            'id' => 'freeshipping_freeshipping',
            'amount' => 0,
            'displayName' => __('eDelivery')
        ];

        return $shippingRates;
    }

    protected function getShippingAddressRequiredRate()
    {
        $shippingRates[] = [
            'id' => 'freeshipping_freeshipping',
            'amount' => 0,
            'displayName' => __('A shipping address is required')
        ];

        return $shippingRates;
    }
    protected function getDefaultShippingRates()
    {
        $countryCode = $this->getCountry();
        $estimateAddress = $this->estimateAddressFactory->create();
        $estimateAddress->setCountryId($countryCode);

        $shippingMethods = $this->getActiveShippingMethods();

        // Process the shipping methods to extract the required information
        $shippingRates = [];
        foreach ($shippingMethods as $shippingMethod) {
            $shippingRates[] = [
                'id' => $shippingMethod['carrier_code'] . '_' . $shippingMethod['method_code'],
                'amount' => 0,
                'displayName' => $shippingMethod['carrier_title'] . ' - ' . $shippingMethod['method_title']
            ];
        }

        return $shippingRates;
    }

    /**
     * Get Country Code
     * @return string
     */
    protected function getCountry()
    {
        $countryCode = $this->quote->getBillingAddress()->getCountryId();
        if (empty($countryCode)) {
            $countryCode = $this->getDefaultCountry();
        }
        return $countryCode;
    }

    /**
     * Return default country code
     *
     * @param \Magento\Store\Model\Store|string|int $store
     * @return string
     */
    protected function getDefaultCountry($store = null)
    {
        $countryId = $this->directoryHelper->getDefaultCountry($store);

        if ($countryId)
            return $countryId;

        return $this->scopeConfig->getValue('general/country/default', ScopeInterface::SCOPE_WEBSITES);
    }

    protected function getActiveShippingMethods()
    {
        $activeCarriers = $this->shippingConfig->getActiveCarriers();

        $shippingMethods = [];
        foreach ($activeCarriers as $carrierCode => $carrierModel) {
            if ($carrierModel->isActive()) {
                $allowedMethods = $carrierModel->getAllowedMethods();
                foreach ($allowedMethods as $methodCode => $methodTitle) {
                    $shippingMethods[] = [
                        'id' => $carrierCode . '_' . $methodCode,
                        'carrier_code' => $carrierCode,
                        'carrier_title' => $carrierModel->getConfigData('title'), // 'Flat Rate
                        'method_code' => $methodCode,
                        'method_title' => $methodTitle
                    ];
                }
            }
        }

        return $shippingMethods;
    }

    protected function getCurrencyFromQuote()
    {
        $currency = $this->quote->getQuoteCurrencyCode();
        if (empty($currency)) {
            $currency = $this->quote->getStore()->getCurrentCurrency()->getCode();
        }
        return $currency;
    }

    /**
     * Should Cart Price Include Tax
     *
     * @return bool
     */
    protected function shouldCartPriceInclTax()
    {
        $store = $this->quote->getStore();

        if ($this->taxHelper->displayCartBothPrices($store)) {
            return true;
        } elseif ($this->taxHelper->displayCartPriceInclTax($store)) {
            return true;
        }

        return false;
    }

    /**
     * Get Line Items
     *
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getLineItems()
    {
        // Get Currency
        $currency = $this->quote->getQuoteCurrencyCode();
        if (empty($currency)) {
            $currency = $this->quote->getStore()->getCurrentCurrency()->getCode();
        }

        // Get Quote Items
        $lineItems = [];
        $this->quote->collectTotals();
        $totals = $this->quote->getTotals();
        $grandTotal = 0;

        foreach ($totals as $total)
        {
            $code = $total->getCode();
            $title = $total->getTitle();
            $value = $total->getValue();

            if ($code == "grand_total")
                continue;

            if (!is_numeric($value))
                continue;

            if ($value == 0 && $code != "tax")
                continue;

            $lineItems[] = [
                'name' => $title,
                'amount' => $this->helper->convertMagentoAmountToStripeAmount($value, $currency, true),
            ];

            $grandTotal += $value;
        }

        if ($this->quote->getGrandTotal() != $grandTotal)
        {
            return [[
                'name' => __('Grand Total'),
                'amount' => $this->helper->convertMagentoAmountToStripeAmount($this->quote->getGrandTotal(), $currency, true),
            ]];
        }

        return $lineItems;
    }

    /**
     * Get Product Price with(without) Taxes
     * @param \Magento\Catalog\Model\Product $product
     * @param float|null $price
     * @param bool $inclTax
     * @param int $customerId
     * @param int $storeId
     *
     * @return float
     */
    protected function getProductDataPrice($product, $price = null, $inclTax = false, $customerId = null, $storeId = null)
    {
        if (!($taxAttribute = $product->getCustomAttribute('tax_class_id')))
            return $price;

        if (!$price) {
            $price = $product->getPrice();
        }

        $productRateId = $taxAttribute->getValue();
        $rate = $this->taxCalculation->getCalculatedRate($productRateId, $customerId, $storeId);
        if ((int) $this->scopeConfig->getValue(
            'tax/calculation/price_includes_tax',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) === 1
        ) {
            $priceExclTax = $price / (1 + ($rate / 100));
        } else {
            $priceExclTax = $price;
        }

        $priceInclTax = $priceExclTax + ($priceExclTax * ($rate / 100));

        return round($inclTax ? floatval($priceInclTax) : floatval($priceExclTax), PriceCurrencyInterface::DEFAULT_PRECISION);
    }

    /**
     * Get allowed countries
     *
     * @return array
     * An array of country codes (e.g., ['US', 'CA'])
     */
    protected function getAllowedShippingCountries()
    {
        $storeScope = ScopeInterface::SCOPE_STORES;
        $countries = $this->allowedCountries->getAllowedCountries($storeScope);
        $unsupportedCountries = ["AS", "CX", "CC", "CU", "HM", "IR", "MH", "FX", "FM", "AN", "NF", "KP", "MP", "PW", "SD", "SY", "VI", "UM"];
        foreach ($unsupportedCountries as $countryCode)
        {
            if (isset($countries[$countryCode]))
                unset($countries[$countryCode]);
        }
        return $countries;
    }

    /**
     * Get Default Shipping Address
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getDefaultShippingAddress()
    {
        $address = [];
        $address['country'] = $this->config->getValue(Shipment::XML_PATH_STORE_COUNTRY_ID, ScopeInterface::SCOPE_STORE, $this->storeId);
        $address['postalCode'] = $this->config->getValue(Shipment::XML_PATH_STORE_ZIP, ScopeInterface::SCOPE_STORE, $this->storeId);
        $address['city'] = $this->config->getValue(Shipment::XML_PATH_STORE_CITY, ScopeInterface::SCOPE_STORE, $this->storeId);
        $address['addressLine'] = [];
        $address['addressLine'][0] = $this->config->getValue(Shipment::XML_PATH_STORE_ADDRESS1, ScopeInterface::SCOPE_STORE, $this->storeId);
        $address['addressLine'][1] = $this->config->getValue(Shipment::XML_PATH_STORE_ADDRESS2, ScopeInterface::SCOPE_STORE, $this->storeId);
        $regionId = $this->config->getValue(Shipment::XML_PATH_STORE_REGION_ID, ScopeInterface::SCOPE_STORE, $this->storeId);
        if ($regionId) {
            $region = $this->region->load($regionId);
            $address['region_id'] = $region->getRegionId();
            $address['region'] = $region->getName();
        }

        return $address;
    }
}