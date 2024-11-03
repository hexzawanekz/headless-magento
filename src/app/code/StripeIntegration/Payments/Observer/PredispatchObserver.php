<?php

namespace StripeIntegration\Payments\Observer;

use Magento\Framework\Event\ObserverInterface;

class PredispatchObserver implements ObserverInterface
{
    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    private $eventManager;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    private $request;

    public function __construct(
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Framework\App\RequestInterface $request
    ) {
        $this->eventManager = $eventManager;
        $this->request = $request;
    }

    /**
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $requestUri = $this->request->getRequestUri();
        if (!empty($requestUri) && stripos($requestUri, "directory/currency/switch") !== false) {
            $currency = $this->request->getParam('currency');
            $this->eventManager->dispatch('stripe_payments_currency_switch', ['currency_code' => $currency]);
        }
    }
}
