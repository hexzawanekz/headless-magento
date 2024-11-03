<?php

namespace StripeIntegration\Payments\Exception;

class OrderPlacedAndPaidException extends \Exception
{
    private $amountsMatch;

    public function __construct($amountsMatch)
    {
        $this->amountsMatch = $amountsMatch;
        parent::__construct();
    }

    public function orderAmountMatchesPaymentAmount()
    {
        return $this->amountsMatch;
    }
}
