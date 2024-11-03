<?php

$objectManager = \Magento\TestFramework\ObjectManager::getInstance();
$configResource = $objectManager->get(\Magento\Config\Model\ResourceModel\Config::class);
$searchCriteriaBuilder = $objectManager->get(\Magento\Framework\Api\SearchCriteriaBuilder::class);
$taxClassRepository = $objectManager->get(\Magento\Tax\Api\TaxClassRepositoryInterface::class);

$searchCriteriaBuilder->addFilter('class_name', 'General Class');
$classes = $taxClassRepository->getList($searchCriteriaBuilder->create())->getItems();
$class = array_pop($classes);

$configResource->saveConfig('tax/stripe_tax/enabled', 0);
$configResource->saveConfig('tax/classes/shipping_tax_class', $class->getId());
$configResource->saveConfig('tax/stripe_tax/enabled', 0, 'stores', 1);

$objectManager->get(\Magento\Framework\App\Config\ReinitableConfigInterface::class)->reinit();
$objectManager->create(\Magento\Store\Model\StoreManagerInterface::class)->reinitStores();