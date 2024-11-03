<?php

namespace StripeIntegration\Payments\Model\Stripe;

class SubscriptionSchedule
{
    use StripeObjectTrait;
    private $objectSpace = 'subscriptionSchedules';

    public function __construct(
        \StripeIntegration\Payments\Model\Stripe\Service\StripeObjectServicePool $stripeObjectServicePool
    )
    {
        $stripeObjectService = $stripeObjectServicePool->getStripeObjectService($this->objectSpace);
        $this->setData($stripeObjectService);
    }

    public function getNextBillingTimestamp()
    {
        $nextPhase = $this->getNextPhase();
        if (empty($nextPhase->start_date))
            return null;

        return $nextPhase->start_date;
    }

    private function getNextPhase()
    {
        /** @var \Stripe\SubscriptionSchedule $stripeObject */
        $stripeObject = $this->getStripeObject();

        if (empty($stripeObject->current_phase->end_date))
            return null;

        foreach ($stripeObject->phases as $phase)
        {
            if ($phase->start_date == $stripeObject->current_phase->end_date)
                return $phase;
        }

        return null;
    }
}