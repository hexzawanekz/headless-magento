<?php

namespace StripeIntegration\Tax\Logger;

use \Monolog\Logger;

class TaxLogger extends Logger
{
    public function __construct(
        $name = 'TaxLogger',
        array $handlers = [],
        array $processors = []
    )
    {
        parent::__construct($name, $handlers, $processors);
    }
}
