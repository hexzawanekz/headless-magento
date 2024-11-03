<?php

namespace StripeIntegration\Payments\Helper;

class RequestCache
{
    private static $cache = [];
    private $config;

    public function __construct(
        \StripeIntegration\Payments\Model\Config $config
    )
    {
        $this->config = $config;
    }

    public function get($key)
    {
        $pk = $this->getConfig()->getSecretKey();
        $key = $pk . "_" . $key;

        if (isset(self::$cache[$key]))
        {
            return self::$cache[$key];
        }
        else
        {
            return null;
        }
    }

    public function set($key, $value)
    {
        $pk = $this->getConfig()->getSecretKey();
        $key = $pk . "_" . $key;
        self::$cache[$key] = $value;
    }

    public function delete($key)
    {
        $pk = $this->getConfig()->getSecretKey();
        $key = $pk . "_" . $key;
        unset(self::$cache[$key]);
    }

    public function clear()
    {
        self::$cache = [];
    }

    protected function getConfig()
    {
        return $this->config;
    }
}