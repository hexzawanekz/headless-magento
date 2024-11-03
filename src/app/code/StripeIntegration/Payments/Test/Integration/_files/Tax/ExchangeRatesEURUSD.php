<?php
use Magento\Directory\Model\Currency;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();

$rates = [
    'EUR' => [
        'USD' => '1.1765',
    ]
];

$currencyModel = $objectManager->create(Currency::class);
$currencyModel->saveRates($rates);