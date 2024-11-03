<?php

namespace StripeIntegration\Tax\Block\Adminhtml\System\Config\Form\Field;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Data\Form\Element\Renderer\RendererInterface;

class ActiveTaxRegistrations extends Template implements RendererInterface
{
    private $isTestMode = false;
    private $config;
    private $configHelper;
    private $countryFactory;
    private $activeTaxRegistrations = null;

    /**
     * ActiveTaxRegistrations constructor.
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        \StripeIntegration\Tax\Model\Config $config,
        \StripeIntegration\Tax\Helper\Config $configHelper,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        Context $context,
        array $data = []
    )
    {
        parent::__construct($context, $data);
        $this->setTemplate('StripeIntegration_Tax::system/config/form/field/active_tax_registrations.phtml');
        $this->config = $config;
        $this->configHelper = $configHelper;
        $this->countryFactory = $countryFactory;

        $this->isTestMode = $this->configHelper->getStripeMode() == 'test';
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

    /**
     * Get active tax registrations
     *
     * @return array
     */
    public function getTaxRegistrations()
    {
        $registrations = $this->_getTaxRegistrations();

        if (empty($registrations)) {
            return __("You are not currently collecting tax for any jurisdictions.");
        }

        return implode(', ', $registrations);
    }

    public function getIsActiveClass()
    {
        $registrations = $this->_getTaxRegistrations();

        if (empty($registrations)) {
            return 'inactive';
        }

        return 'active';
    }

    public function hasActiveRegistrations()
    {
        $registrations = $this->_getTaxRegistrations();

        return !empty($registrations);
    }

    /**
     * Get tax registrations
     *
     * @return array
     */
    private function _getTaxRegistrations()
    {
        if ($this->activeTaxRegistrations === null)
        {
            $this->activeTaxRegistrations = [];
            try
            {
                $registrations = $this->config->getStripeClient()->tax->registrations->all(['status' => 'active']);
                foreach ($registrations->autoPagingIterator() as $registration)
                {
                    $countryCode = $registration->country;
                    $countryName = $this->getCountryNameByCode($countryCode);
                    $this->activeTaxRegistrations[] = $countryName;
                }
            }
            catch (\Exception $e)
            {

            }
        }

        return $this->activeTaxRegistrations;
    }

    public function getTestPrefix()
    {
        if ($this->isTestMode)
            return 'test/';
        else
            return '';
    }

    /**
     * Get country name by code
     *
     * @param string $countryCode
     * @return string
     */
    private function getCountryNameByCode($countryCode)
    {
        $country = $this->countryFactory->create()->loadByCode($countryCode);
        return $country->getName();
    }
}
