<?php

namespace StripeIntegration\Tax\Block\Adminhtml;

use StripeIntegration\Tax\Exceptions\Exception;

class TaxClasses extends \Magento\Backend\Block\Template
{
    private $config;
    private $logger;
    private $taxClassCollection;
    private $serializer;
    private $storeHelper;

    public function __construct(
        \StripeIntegration\Tax\Model\Config $config,
        \StripeIntegration\Tax\Helper\Store $storeHelper,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Tax\Model\ResourceModel\TaxClass\Collection $taxClassCollection,
        \Magento\Framework\Serialize\SerializerInterface $serializer,
        \Magento\Backend\Block\Template\Context $context,
        array $data = []
    ) {
        $this->config = $config;
        $this->storeHelper = $storeHelper;
        $this->logger = $logger;
        $this->taxClassCollection = $taxClassCollection;
        $this->serializer = $serializer;
        parent::__construct($context, $data);
    }

    // Returns a list of all existing Magento tax classes
    private function getTaxClasses()
    {
        $taxClasses = [];

        foreach ($this->taxClassCollection as $taxClass)
        {
            if ($taxClass->getClassType() == \Magento\Tax\Model\ClassModel::TAX_CLASS_TYPE_PRODUCT) {
                $taxClasses[] = $taxClass->getData();
            }
        }
        return $this->serializer->serialize($taxClasses);
    }

    private function getProductTaxCodes()
    {
        $data = [];

        try
        {
            $taxCodes = $this->getStripeClient()->taxCodes->all(['limit' => 100]);
            foreach ($taxCodes->autoPagingIterator() as $taxCode) {
                $data[] = $taxCode;
            }
        }
        catch (\Exception $e)
        {
            $this->logger->critical("Error fetching tax codes from Stripe: " . $e->getMessage());
        }

        return $this->serializer->serialize($data);
    }

    private function getStripeClient()
    {
        if ($this->config->getStripeClient())
            return $this->config->getStripeClient();

        // If the current store is not connected to Stripe, find the first one that is
        // The product tax codes will be the same across all Stripe accounts
        $stores = $this->storeHelper->getStores();
        foreach ($stores as $store)
        {
            try
            {
                $this->config->reInitStripeFromStore($store);
                if ($this->config->getStripeClient())
                    return $this->config->getStripeClient();
            }
            catch (\Exception $e)
            {
                continue;
            }
        }

        throw new Exception("Could not find a store connected to Stripe. Please connect a store to Stripe first.");
    }

    public function getConfig()
    {
        $config = [
            'taxClasses' => $this->getTaxClasses(),
            'productTaxCodes' => $this->getProductTaxCodes(),
            'formKey' => $this->getFormKey()
        ];

        return $this->serializer->serialize($config);
    }
}