<?php

namespace StripeIntegration\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;

class CurrencySwitchObserver implements ObserverInterface
{
    private $config;
    private $helper;
    private $paymentsHelper;
    private $serializer;

    public function __construct(
        \StripeIntegration\Payments\Helper\InitialFee $helper,
        \StripeIntegration\Payments\Helper\Generic $paymentsHelper,
        \StripeIntegration\Payments\Model\Config $config,
        \Magento\Framework\Serialize\SerializerInterface $serializer
    )
    {
        $this->helper = $helper;
        $this->paymentsHelper = $paymentsHelper;
        $this->config = $config;
        $this->serializer = $serializer;
    }

    /**
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->config->isSubscriptionsEnabled())
            return;

        if (!$this->config->getConfigData("additional_info", "subscriptions"))
            return;

        $currencyCode = $observer->getCurrencyCode();

        $items = $this->paymentsHelper->getSessionQuote()->getAllItems();
        foreach ($items as $item)
        {
            $additionalOptions = $this->helper->getAdditionalOptionsForQuoteItem($item, $currencyCode);

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

                    $item->save();
                }
            }
        }
    }
}
