<?php

namespace StripeIntegration\Payments\Block\Method;

use Magento\Payment\Block\ConfigurableInfo;

class Checkout extends ConfigurableInfo
{
    protected $_template = 'form/checkout.phtml';

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Payment\Gateway\ConfigInterface $config,
        array $data = []
    ) {
        parent::__construct($context, $config, $data);
    }
}
