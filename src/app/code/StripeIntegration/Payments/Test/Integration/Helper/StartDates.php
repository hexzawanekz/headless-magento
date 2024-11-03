<?php

namespace StripeIntegration\Payments\Test\Integration\Helper;

class StartDates
{
    private $day = "10";

    public function __construct()
    {
        // It would be funny if our tests failed on the 10th of each month
        if (date('d') == "10")
            $this->day = "20";
    }

    public function getStartDate()
    {
        return "2021-01-" . $this->day;
    }

    public function getStartDay()
    {
        return $this->day;
    }
}