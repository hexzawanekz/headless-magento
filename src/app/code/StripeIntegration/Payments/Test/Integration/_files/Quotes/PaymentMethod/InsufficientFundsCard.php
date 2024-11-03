<?php

$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
$quoteRepository = $objectManager->create(\Magento\Quote\Api\CartRepositoryInterface::class);

$quote = $objectManager->create(\Magento\Quote\Model\Quote::class);
$quote->load('test_quote', 'reserved_order_id');

$data = [
    'method' => 'stripe_payments',
    'additional_data' => [
        "payment_method" => "pm_card_chargeDeclinedInsufficientFunds"
    ]
];
$quote->getPayment()->importData($data);

$quoteRepository = $objectManager->create(\Magento\Quote\Api\CartRepositoryInterface::class);
$quoteRepository->save($quote);
