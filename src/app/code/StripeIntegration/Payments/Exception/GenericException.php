<?php

namespace StripeIntegration\Payments\Exception;

class GenericException extends \Exception
{
    public $statusCode;
}
