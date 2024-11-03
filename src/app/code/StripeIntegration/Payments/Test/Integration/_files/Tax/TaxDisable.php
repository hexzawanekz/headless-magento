<?php

$objectManager = \Magento\TestFramework\ObjectManager::getInstance();
$configResource = $objectManager->get(\Magento\Config\Model\ResourceModel\Config::class);

$configResource->saveConfig('tax/stripe_tax/enabled', 0, 'stores', 1);
$configResource->saveConfig('tax/stripe_tax/stripe_mode', "test", 'stores', 1);

$objectManager->get(\Magento\Framework\App\Config\ReinitableConfigInterface::class)->reinit();
$objectManager->create(\Magento\Store\Model\StoreManagerInterface::class)->reinitStores();