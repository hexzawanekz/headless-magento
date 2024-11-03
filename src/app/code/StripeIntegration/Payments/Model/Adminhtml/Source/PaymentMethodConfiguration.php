<?php

namespace StripeIntegration\Payments\Model\Adminhtml\Source;

class PaymentMethodConfiguration
{
    private $config;
    private $request;
    private $configurations;

    public function __construct(
        \StripeIntegration\Payments\Model\Config $config,
        \Magento\Framework\App\Request\Http $request
    )
    {
        $this->config = $config;
        $this->request = $request;

        $storeId = $this->request->getParam('store', null);
        if ($storeId)
        {
            $this->config->reInitStripeFromStoreId($storeId);
        }

        if ($this->config->initStripe())
            $this->configurations = $this->config->getStripeClient()->paymentMethodConfigurations->all();
        else
            $this->configurations = null;
    }

    public function toOptionArray()
    {
        $options =  [[
            'value' => null,
            'label' => 'Disabled',
            'is_default' => true
        ]];

        if (empty($this->configurations))
            return $options;

        foreach ($this->configurations->autoPagingIterator() as $configuration)
        {
            if (!$configuration->active)
                continue;

            if ($configuration->application) // This is a Stripe Connect PMC
                continue;

            $option = [
                'value' => $configuration->id,
                'label' => $configuration->name,
                'is_default' => false //$configuration->is_default
            ];

            $options[] = $option;
        }

        return $options;
    }
}
