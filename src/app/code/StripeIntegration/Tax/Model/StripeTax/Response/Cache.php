<?php

namespace StripeIntegration\Tax\Model\StripeTax\Response;

class Cache
{
    private static $cache = [];
    public function get($key)
    {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        } else {
            return 0;
        }
    }

    public function set($key, $value)
    {
        self::$cache[$key] = $value;
    }

    public function delete($key)
    {
        unset(self::$cache[$key]);
    }

    public function clear()
    {
        self::$cache = [];
    }
}