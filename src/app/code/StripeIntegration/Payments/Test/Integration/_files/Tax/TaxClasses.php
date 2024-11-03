<?php

$objectManager = \Magento\TestFramework\ObjectManager::getInstance();

// Get the collection for the tax_class database table
$taxClassCollection = $objectManager->create(\Magento\Tax\Model\ClassModelFactory::class)->create()->getCollection();

foreach ($taxClassCollection as $taxClass) {
    if ($taxClass->getClassType() != 'PRODUCT')
        continue;

    // txcd_99999999 = General - Tangible Goods
    $taxClass->setStripeProductTaxCode('txcd_99999999');
    $taxClass->save();
}
