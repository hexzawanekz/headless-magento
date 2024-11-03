<?php

namespace StripeIntegration\Payments\Block\Adminhtml\Payment;

// Simple phtml template
class AdminStripeParams extends \Magento\Framework\View\Element\Template
{
    private $initParams;
    protected $_template = 'form/stripe_init_params.phtml';

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \StripeIntegration\Payments\Helper\InitParams $initParams,
        $data = []
    ) {
        parent::__construct($context, $data);

        $this->initParams = $initParams;
    }

    public function getAdminStripeParams()
    {
        return $this->initParams->getAdminParams();
    }
}
