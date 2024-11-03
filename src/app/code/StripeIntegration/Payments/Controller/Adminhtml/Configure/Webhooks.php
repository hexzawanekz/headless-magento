<?php

namespace StripeIntegration\Payments\Controller\Adminhtml\Configure;

use Magento\Framework\App\ActionInterface;

class Webhooks implements ActionInterface
{
    private $resultJsonFactory;
    private $webhooksSetup;

    public function __construct(
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \StripeIntegration\Payments\Helper\WebhooksSetup $webhooksSetup
    )
    {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->webhooksSetup = $webhooksSetup;
    }

    public function execute()
    {
        $this->webhooksSetup->configure();
        $result = $this->resultJsonFactory->create();
        return $result->setData(['success' => true, 'errors' => count($this->webhooksSetup->errorMessages)]);
    }
}
