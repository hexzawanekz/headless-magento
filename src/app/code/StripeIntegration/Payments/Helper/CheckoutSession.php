<?php

namespace StripeIntegration\Payments\Helper;

use StripeIntegration\Payments\Exception\GenericException;

class CheckoutSession
{
    private $checkoutSession;

    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession
    )
    {
        $this->checkoutSession = $checkoutSession;
    }

    public function isSubscriptionUpdate()
    {
        $updateDetails = $this->checkoutSession->getSubscriptionUpdateDetails();

        return !empty($updateDetails['_data']['subscription_id']);
    }

    public function isSubscriptionReactivate()
    {
        $reactivateDetails = $this->checkoutSession->getSubscriptionReactivateDetails();

        return !empty($reactivateDetails['update_subscription_id']);
    }

    public function getSubscriptionUpdateDetails()
    {
        $subscriptionUpdateDetails = $this->checkoutSession->getSubscriptionUpdateDetails();
        if (!$subscriptionUpdateDetails || empty($subscriptionUpdateDetails['_data']['subscription_id']))
            throw new GenericException("The subscription update details could not be read from the checkout session.");

        return $subscriptionUpdateDetails;
    }

    public function getSubscriptionReactivateDetails()
    {
        $subscriptionReactivateDetails = $this->checkoutSession->getSubscriptionReactivateDetails();

        return $subscriptionReactivateDetails;
    }
}