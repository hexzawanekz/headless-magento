<?php

namespace StripeIntegration\Payments\Model\Stripe;

use StripeIntegration\Payments\Exception\GenericException;

class PaymentIntent
{
    use StripeObjectTrait;

    private $objectSpace = 'paymentIntents';
    private $tokenHelper;
    private $paymentIntentHelper;

    public function __construct(
        \StripeIntegration\Payments\Model\Stripe\Service\StripeObjectServicePool $stripeObjectServicePool,
        \StripeIntegration\Payments\Helper\Token $tokenHelper,
        \StripeIntegration\Payments\Helper\PaymentIntent $paymentIntentHelper
    )
    {
        $stripeObjectService = $stripeObjectServicePool->getStripeObjectService($this->objectSpace);
        $this->setData($stripeObjectService);

        $this->tokenHelper = $tokenHelper;
        $this->paymentIntentHelper = $paymentIntentHelper;
    }

    public function fromPaymentIntentId($id, $expandParams = [])
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

    public function fromObject(\Stripe\PaymentIntent $paymentIntent)
    {
        $this->setObject($paymentIntent);
        return $this;
    }

    public function setExpandParams($params)
    {
        $this->stripeObjectService->setExpandParams($params);

        return $this;
    }

    public function isFinalized()
    {
        $paymentIntent = $this->getStripeObject();
        if (!$paymentIntent)
            return false;

        return $this->paymentIntentHelper->isSuccessful($paymentIntent) || $this->paymentIntentHelper->requiresOfflineAction($paymentIntent);
    }

    public function getAmount()
    {
        $paymentIntent = $this->getStripeObject();
        if (!$paymentIntent)
        {
            throw new GenericException("Payment intent not loaded.");
        }

        return $paymentIntent->amount;
    }

    public function getCurrency()
    {
        $paymentIntent = $this->getStripeObject();
        if (!$paymentIntent)
        {
            throw new GenericException("Payment intent not loaded.");
        }

        return $paymentIntent->currency;
    }
}