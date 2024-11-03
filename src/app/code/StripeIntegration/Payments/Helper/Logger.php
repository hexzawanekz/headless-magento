<?php

namespace StripeIntegration\Payments\Helper;

use Psr\Log\LoggerInterface;
use StripeIntegration\Payments\Model\Config;

class Logger
{
    private $logger;
    private $serializer;

    public function __construct(
        LoggerInterface $logger,
        \Magento\Framework\Serialize\SerializerInterface $serializer
    )
    {
        $this->logger = $logger;
        $this->serializer = $serializer;
    }

    public function getPrintableObject($obj)
    {
        if (is_object($obj))
        {
            if (method_exists($obj, 'debug'))
                $data = $obj->debug();
            else if (method_exists($obj, 'getData'))
                $data = $obj->getData();
            else
                $data = $obj;
        }
        else
            $data = $obj;

        if (is_array($data) || is_object($data))
        {
            $data = $this->serializer->serialize($data);
            $data = $this->serializer->unserialize($data);
            $data = $this->prettyStringFromArray($data, 5);
        }

        return $data;
    }

    public function log($obj)
    {
        $data = $this->getPrintableObject($obj);
        $this->logger->error($data);
    }

    public function logError(string $msg, $trace = null)
    {
        if ($this->isAuthenticationRequiredMessage($msg))
            return;

        $entry = Config::$moduleName . " v" . Config::$moduleVersion . ": " . $msg;

        if ($trace)
            $entry .= "\n$trace";

        $this->logger->error($entry);
    }

    public function logInfo(?string $msg)
    {
        $entry = Config::$moduleName . " v" . Config::$moduleVersion . ": " . $msg;
        $this->logger->info($entry);
    }

    public function backtrace()
    {
        $e = new \Exception();
        $trace = explode("\n", $e->getTraceAsString());

        array_pop($trace); // remove {main}
        array_shift($trace); // remove call to this method

        $this->log("\n\t" . implode("\n\t", $trace));
    }

    // var_export and json_decode($data, JSON_PRETTY_PRINT, 5) are no longer allowed in coding standards
    private function prettyStringFromArray(array $data, $maxDepth = 5, $currentDepth = 0)
    {
        if ($currentDepth == 0)
        {
            $indentation = "    ";
            $result = $this->getType($data) . "\n";
        }
        else
        {
            $indentation = "    " . str_repeat("|   ", $currentDepth);
            $result = "";
        }

        foreach ($data as $key => $value)
        {
            if (is_array($value))
            {
                if ($currentDepth < $maxDepth) {
                    $nestedData = $this->prettyStringFromArray($value, $maxDepth, $currentDepth + 1);
                    $result .= $indentation . "[$key] => " . $this->getType($value) . "\n" . $nestedData;
                } else {
                    $result .= $indentation . "[$key] => " . $this->getType($value) . "\n";
                }
            }
            else {
                $result .= $indentation . "[$key] => " . $value . "\n";
            }
        }

        return $result;
    }

    // In Magento coding standards, the use of function gettype() is discouraged, so we use a custom one
    private function getType($value)
    {
        if (is_array($value))
            return "Array";
        else if (is_object($value))
            return "Object";
        else
            return null;
    }

    public function shouldLogExceptionTrace($e)
    {
        if (empty($e))
            return false;

        $msg = $e->getMessage();
        if ($this->isAuthenticationRequiredMessage($msg))
            return false;

        if (get_class($e) == \Stripe\Exception\CardException::class) // i.e. card declined, insufficient funds etc
            return false;

        if (get_class($e) == \Magento\Framework\Exception\CouldNotSaveException::class)
        {
            switch ($msg)
            {
                case "Your card was declined.":
                    return false;
                default:
                    break;
            }
        }

        return true;
    }

    public function isAuthenticationRequiredMessage($message)
    {
        return (strpos($message, "Authentication Required: ") !== false);
    }
}
