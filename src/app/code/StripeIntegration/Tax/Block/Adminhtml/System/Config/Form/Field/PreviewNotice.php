<?php

namespace StripeIntegration\Tax\Block\Adminhtml\System\Config\Form\Field;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Data\Form\Element\Renderer\RendererInterface;

class PreviewNotice extends Template implements RendererInterface
{
    /**
     * ActiveTaxRegistrations constructor.
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Context $context,
        array $data = []
    )
    {
        parent::__construct($context, $data);
        $this->setTemplate('StripeIntegration_Tax::system/config/form/field/preview_notice.phtml');
    }

    /**
     * Render the element
     *
     * @param AbstractElement $element
     * @return string
     */
     public function render(AbstractElement $element)
     {
         $element->addClass('admin__control-text');
         $this->setElement($element);
         return $this->toHtml();
     }
}
