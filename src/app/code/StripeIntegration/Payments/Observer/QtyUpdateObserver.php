<?php

namespace StripeIntegration\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;

class QtyUpdateObserver implements ObserverInterface
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
        if (!$this->config->getConfigData("additional_info", "subscriptions"))
            return;

        $items = $observer->getCart()->getQuote()->getItems();
        if (empty($items))
            return;

        foreach ($items as $item)
        {
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
}
