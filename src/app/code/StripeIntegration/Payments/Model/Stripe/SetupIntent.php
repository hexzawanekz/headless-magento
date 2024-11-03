<?php

namespace StripeIntegration\Payments\Model\Stripe;

class SetupIntent
{
    use StripeObjectTrait;

    public const CANCELABLE_STATUSES = ['requires_payment_method', 'requires_confirmation', 'requires_action'];

    private $objectSpace = 'setupIntents';
    private $tokenHelper;
    private $config;

    public function __construct(
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Model\Stripe\Service\StripeObjectServicePool $stripeObjectServicePool,
        \StripeIntegration\Payments\Helper\Token $tokenHelper
    )
    {
        $stripeObjectService = $stripeObjectServicePool->getStripeObjectService($this->objectSpace);
        $this->setData($stripeObjectService);

        $this->config = $config;
        $this->tokenHelper = $tokenHelper;
    }

    public function fromSetupIntentId($id, $expandParams = [])
    {
        $id = $this->tokenHelper->cleanToken($id);

        if (!empty($this->getStripeObject()->id) && $this->getStripeObject()->id == $id)
        {
            return $this;
        }

        $this->setExpandParams($expandParams);
        $this->load($id);
        return $this;
    }

    public function fromParams($params)
    {
        $this->createObject($params);
        return $this;
    }

    public function fromObject(\Stripe\SetupIntent $setupIntent)
    {
        $this->setObject($setupIntent);
        return $this;
    }

    public function setExpandParams($params)
    {
        $this->stripeObjectService->setExpandParams($params);

        return $this;
    }

    public function cancel()
    {
        $setupIntent = $this->getStripeObject();
        if ($setupIntent && in_array($setupIntent->status, self::CANCELABLE_STATUSES))
        {
            $this->reset();
            $this->config->getStripeClient()->setupIntents->cancel($setupIntent->id);
        }
    }
}