<?php

namespace StripeIntegration\Payments\Controller\Webhooks;

use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;

class Index implements CsrfAwareActionInterface
{
    private $webhooks;
    private $response;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \StripeIntegration\Payments\Helper\Webhooks $webhooks
    )
    {
        $this->webhooks = $webhooks;
        $this->response = $context->getResponse();
    }

    /**
     * @return ResponseInterface
     */
    public function execute()
    {
        $this->webhooks->dispatchEvent();

        return $this->response;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
