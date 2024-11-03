<?php

namespace StripeIntegration\Payments\Model;

use StripeIntegration\Payments\Exception\GenericException;

class SetupIntent extends \Magento\Framework\Model\AbstractModel
{
    private $setupIntentHelper;
    private $resourceModel;
    private $stripeSetupIntentFactory;
    private $stripeSetupIntentModel = null;

    public function __construct(
        \StripeIntegration\Payments\Helper\SetupIntent $setupIntentHelper,
        \StripeIntegration\Payments\Model\ResourceModel\SetupIntent $resourceModel,
        \StripeIntegration\Payments\Model\Stripe\SetupIntentFactory $stripeSetupIntentFactory,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
        )
    {
        $this->setupIntentHelper = $setupIntentHelper;
        $this->resourceModel = $resourceModel;
        $this->stripeSetupIntentFactory = $stripeSetupIntentFactory;

        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    protected function _construct()
    {
        $this->_init('StripeIntegration\Payments\Model\ResourceModel\SetupIntent');
    }

    public function hydrate()
    {
        if ($this->getSiId())
        {
            $this->stripeSetupIntentModel = $this->stripeSetupIntentFactory->create()->fromSetupIntentId($this->getSiId());
        }

        return $this;
    }

    public function initFromOrder($order)
    {
        $paymentMethodId = $order->getPayment()->getAdditionalInformation('token');
        if (empty($paymentMethodId))
            throw new GenericException("No payment method token is set for the order.");

        $this->setOrderIncrementId($order->getIncrementId());
        $this->setQuoteId($order->getQuoteId());
        $this->setPmId($paymentMethodId);

        if ($this->getSiId())
        {
            $stripeSetupIntentModel = $this->stripeSetupIntentFactory->create()->fromSetupIntentId($this->getSiId());
            $setupIntent = $stripeSetupIntentModel->getStripeObject();
            if ($setupIntent && $setupIntent->payment_method == $paymentMethodId)
            {
                $this->stripeSetupIntentModel = $stripeSetupIntentModel;
            }
        }

        if (!$this->stripeSetupIntentModel)
        {
            // Create and automatically confirm the setup intent using the order's payment method
            $createParams = $this->setupIntentHelper->getCreateParams($order);
            $this->stripeSetupIntentModel = $this->stripeSetupIntentFactory->create()->fromParams($createParams);
        }

        // This should always be a confirmed SetupIntent, with a matching payment method
        $setupIntent = $this->stripeSetupIntentModel->getStripeObject();

        $this->setSiId($this->stripeSetupIntentModel->getId());

        // Post confirmation checks
        if ($setupIntent->status == "requires_payment_method")
        {
            // Typically occurs on payment failures, or something else went wrong with the PM setup
            throw new GenericException("Could not set up payment method.");
        }

        return $this;
    }

    public function save()
    {
        $this->resourceModel->save($this);

        return $this;
    }

    public function requiresMicrodepositsVerification()
    {
        if (!$this->stripeSetupIntentModel)
            return false;

        $setupIntent = $this->stripeSetupIntentModel->getStripeObject();
        return ($setupIntent->status == "requires_action" && $setupIntent->next_action->type == "verify_with_microdeposits");
    }

    public function getStripeObject()
    {
        if ($this->stripeSetupIntentModel)
            return $this->stripeSetupIntentModel->getStripeObject();

        return null;
    }

    public function cancel()
    {
        if ($this->stripeSetupIntentModel)
            $this->stripeSetupIntentModel->cancel();
    }
}