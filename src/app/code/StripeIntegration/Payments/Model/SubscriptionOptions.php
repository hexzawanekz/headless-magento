<?php

namespace StripeIntegration\Payments\Model;

use StripeIntegration\Payments\Api\Data\SubscriptionOptionsInterface;

class SubscriptionOptions extends \Magento\Framework\Model\AbstractModel implements SubscriptionOptionsInterface
{
    protected $configFactory;
    protected $config;

    public function __construct(
        \StripeIntegration\Payments\Model\ConfigFactory $configFactory,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->configFactory = $configFactory;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    protected function _construct()
    {
        $this->_init('StripeIntegration\Payments\Model\ResourceModel\SubscriptionOptions');
    }

    public function getUpgradesDowngrades()
    {
        if ($this->getUpgradesDowngradesUseConfig())
        {
            return $this->getConfig()->getConfigData("upgrade_downgrade", "subscriptions");
        }
        else
        {
            return $this->getData("upgrades_downgrades");
        }
    }

    public function getProrateUpgrades()
    {
        return false; // Prorated subscription updates are temporarily disabled in v4.1

        // if ($this->getProrateUpgradesUseConfig())
        // {
        //     return $this->getConfig()->getConfigData("prorations_upgrades", "subscriptions");
        // }
        // else
        // {
        //     return $this->getData("prorate_upgrades");
        // }
    }

    public function getProrateDowngrades()
    {
        return false; // Prorated subscription updates are temporarily disabled in v4.1

        // if ($this->getProrateDowngradesUseConfig())
        // {
        //     return $this->getConfig()->getConfigData("prorations_downgrades", "subscriptions");
        // }
        // else
        // {
        //     return $this->getData("prorate_downgrades");
        // }
    }

    protected function getConfig()
    {
        if (!isset($this->config)) {
            $this->config = $this->configFactory->create();
        }

        return $this->config;
    }
}
