<?php

namespace StripeIntegration\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;

class AddInitialFeeObserver implements ObserverInterface
{
    private $config;
    private $helper;
    private $serializer;

    public function __construct(
        \StripeIntegration\Payments\Helper\InitialFee $helper,
        \StripeIntegration\Payments\Model\Config $config,
        \Magento\Framework\Serialize\SerializerInterface $serializer
    )
    {
        $this->helper = $helper;
        $this->config = $config;
        $this->serializer = $serializer;
    }

    /**
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        // $item = $observer->getEvent()->getData('quote_item');
        // $item = ( $item->getParentItem() ? $item->getParentItem() : $item );
        // $price = 100; //set your price here
        // $item->setCustomPrice($price);
        // $item->setOriginalCustomPrice($price);
        // $item->getProduct()->setIsSuperMode(true);

        if (!$this->config->isSubscriptionsEnabled())
            return;

        if (!$this->config->getConfigData("additional_info", "subscriptions"))
            return;

        $item = $observer->getQuoteItem();

        if (!$item)
            return;

        $additionalOptions = $this->helper->getAdditionalOptionsForQuoteItem($item);

        if (!empty($additionalOptions))
        {
            $data = $this->serializer->serialize($additionalOptions);

            if ($data)
            {
                $item->addOption([
                    'product_id' => $item->getProductId(),
                    'code' => 'additional_options',
                    'value' => $data
                ]);
            }
        }
    }
}
