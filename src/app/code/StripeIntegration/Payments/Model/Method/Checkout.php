<?php

namespace StripeIntegration\Payments\Model\Method;

use Magento\Framework\Event\ManagerInterface;
use Magento\Payment\Gateway\Command\CommandManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Validator\ValidatorPoolInterface;
use Magento\Payment\Model\InfoInterface;
use Psr\Log\LoggerInterface;

class Checkout extends \Magento\Payment\Model\Method\Adapter
{
    private $config;
    private $helper;
    private $checkoutSessionHelper;
    private $quoteHelper;

    public function __construct(
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Helper\CheckoutSession $checkoutSessionHelper,
        \StripeIntegration\Payments\Helper\Quote $quoteHelper,
        ManagerInterface $eventManager,
        ValueHandlerPoolInterface $valueHandlerPool,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        $code,
        $formBlockType,
        $infoBlockType,
        CommandPoolInterface $commandPool = null,
        ValidatorPoolInterface $validatorPool = null,
        CommandManagerInterface $commandExecutor = null,
        LoggerInterface $logger = null
    ) {
        $this->config = $config;
        $this->helper = $helper;
        $this->checkoutSessionHelper = $checkoutSessionHelper;
        $this->quoteHelper = $quoteHelper;

        parent::__construct(
            $eventManager,
            $valueHandlerPool,
            $paymentDataObjectFactory,
            $code,
            $formBlockType,
            $infoBlockType,
            $commandPool,
            $validatorPool,
            $commandExecutor,
            $logger
        );
    }

    public function getTitle()
    {
        return $this->config->getConfigData("title");
    }

    public function isEnabled($quote)
    {
        return $this->config->isEnabled() &&
            $this->config->isRedirectPaymentFlow() &&
            !$this->helper->isAdmin() &&
            !$this->helper->isMultiShipping() &&
            !$this->checkoutSessionHelper->isSubscriptionUpdate();
    }

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if ($this->helper->isRecurringOrder($this))
            return true;

        if (!$this->isEnabled($quote))
            return false;

        if ($quote && $this->getConfigPaymentAction() == 'order')
        {
            $hasNonBillableSubscriptionItems = !empty($this->quoteHelper->getNonBillableSubscriptionItems($quote->getAllItems()));
            $hasFullyDiscountedSubscriptions = $this->quoteHelper->hasFullyDiscountedSubscriptions($quote);
        }
        else
        {
            $hasNonBillableSubscriptionItems = false;
            $hasFullyDiscountedSubscriptions = false;
        }

        return $hasNonBillableSubscriptionItems ||
            $hasFullyDiscountedSubscriptions ||
            parent::isAvailable($quote);
    }

    public function getConfigPaymentAction()
    {
        return 'order';
    }

    public function canEdit()
    {
        /** @var InfoInterface $info */
        $info = $this->getInfoInstance();

        if (!empty($info->getTransactionId()))
            return false;

        if (!empty($info->getLastTransId()))
            return false;

        if (empty($info->getAdditionalInformation("token")))
            return false;

        if (empty($info->getAdditionalInformation("customer_stripe_id")))
            return false;

        $token = $info->getAdditionalInformation("token");

        if (strpos($token, "pm_") !== 0)
            return false;

        return true;
    }
}
