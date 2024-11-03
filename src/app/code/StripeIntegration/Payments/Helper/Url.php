<?php

namespace StripeIntegration\Payments\Helper;

class Url
{
    private $urlBuilder;
    private $request;

    public function __construct(
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\App\Request\Http $request
    )
    {
        $this->urlBuilder = $urlBuilder;
        $this->request = $request;
    }

    public function getUrl($path, $additionalParams = [])
    {
        $params = ['_secure' => $this->request->isSecure()];
        return $this->urlBuilder->getUrl($path, $params + $additionalParams);
    }
}