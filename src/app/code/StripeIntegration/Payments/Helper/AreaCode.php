<?php

namespace StripeIntegration\Payments\Helper;

class AreaCode
{
    private $state;

    public function __construct(
        \Magento\Framework\App\State $state
    )
    {
        $this->state = $state;
    }

    public function getAreaCode()
    {
        try
        {
            return $this->state->getAreaCode();
        }
        catch (\Exception $e)
        {
            return null;
        }
    }

    // const AREA_GLOBAL = 'global';
    // const AREA_FRONTEND = 'frontend';
    // const AREA_ADMINHTML = 'adminhtml';
    // const AREA_DOC = 'doc';
    // const AREA_CRONTAB = 'crontab';
    // const AREA_WEBAPI_REST = 'webapi_rest';
    // const AREA_WEBAPI_SOAP = 'webapi_soap';
    // const AREA_GRAPHQL = 'graphql';
    public function setAreaCode($code = "global")
    {
        $areaCode = $this->getAreaCode();
        if (!$areaCode)
            $this->state->setAreaCode($code);
    }

    public function isAdmin()
    {
        $areaCode = $this->getAreaCode();

        return $areaCode == \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE;
    }

    public function isGraphQLRequest()
    {
        $areaCode = $this->getAreaCode();

        return ($areaCode == "graphql");
    }

    public function isAPIRequest()
    {
        $areaCode = $this->getAreaCode();

        switch ($areaCode)
        {
            case 'webapi_rest': // \Magento\Framework\App\Area::AREA_WEBAPI_REST:
            case 'webapi_soap': // \Magento\Framework\App\Area::AREA_WEBAPI_SOAP:
            case 'graphql': // \Magento\Framework\App\Area::AREA_GRAPHQL: - Magento 2.1 doesn't have the constant
                return true;
            default:
                return false;
        }
    }
}
