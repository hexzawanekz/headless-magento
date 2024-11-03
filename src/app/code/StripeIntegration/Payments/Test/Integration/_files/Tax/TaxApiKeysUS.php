<?php

$settings = get_defined_constants();

$publicKey = $settings['TAX_API_PK_US_TEST'];
$secretKey = $settings['TAX_API_SK_US_TEST'];

$objectManager = \Magento\TestFramework\ObjectManager::getInstance();
$configResource = $objectManager->get(\Magento\Config\Model\ResourceModel\Config::class);
$configResource->saveConfig(
    'tax/stripe_tax/stripe_test_pk',
    $publicKey,
    'stores',
    1
);
$configResource->saveConfig(
    'tax/stripe_tax/stripe_test_sk',
    $secretKey,
    'stores',
    1
);

$objectManager->get(\Magento\Framework\App\Config\ReinitableConfigInterface::class)->reinit();
$objectManager->create(\Magento\Store\Model\StoreManagerInterface::class)->reinitStores();