<?php

namespace StripeIntegration\Tax\Model\Adminhtml\Frontend;

use \Magento\Config\Block\System\Config\Form\Field;
use StripeIntegration\Tax\Model\StripeTax;

class ConfigManagedByStripe extends Field
{
    protected $_template = 'StripeIntegration_Tax::config/managed_by_stripe.phtml';

    private $stripeTax;

    public function __construct(
        StripeTax $stripeTax,
        \Magento\Backend\Block\Template\Context $context,
        array $data = []
    )
    {
        $this->stripeTax = $stripeTax;

        parent::__construct($context, $data);
    }

    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        if ($this->stripeTax->isEnabled()) {
            $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue()->unsCanRestoreToDefault()->unsComment();
        }
        return parent::render($element);
    }

    public function getText()
    {
        return __('Managed by Stripe Tax');
    }

    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        if ($this->stripeTax->isEnabled()) {
            return $this->_toHtml();
        }

        return parent::_getElementHtml($element);
    }
}