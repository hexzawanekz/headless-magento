<?php

namespace StripeIntegration\Payments\Helper;

use StripeIntegration\Payments\Exception\GenericException;

class Compare
{
    public $lastReason = '';

    // Returns true if the object values are different than $expectedValues
    public function isDifferent($object, array $expectedValues)
    {
        return !$this->isSame($object, $expectedValues);
    }

    // Returns true if the object values are the same as $expectedValues
    public function isSame($object, array $expectedValues)
    {
        try
        {
            $this->lastReason = '';

            $values = json_decode(json_encode($object), true);
            if (!is_array($values))
            {
                $this->lastReason = "is_array";
                return false;
            }

            foreach ($expectedValues as $key => $value)
            {
                $this->compare($values, $expectedValues, $key);
            }

            return true;
        }
        catch (GenericException $e)
        {
            $this->lastReason = $e->getMessage();
            return false;
        }
    }

    public function compare(array $values, array $expectedValues, string $key)
    {
        if ($expectedValues[$key] === "unset")
        {
            if (isset($values[$key]))
                throw new GenericException($key . " should not be set");
            else
                return;
        }
        else if (!isset($values[$key]))
            throw new GenericException($key . " is not set");

        if (is_array($expectedValues[$key]))
        {
            if (!is_array($values[$key]))
                throw new GenericException($key);

            foreach ($expectedValues[$key] as $k => $value)
            {
                $this->compare($values[$key], $expectedValues[$key], $k);
            }
        }
        else
        {
            if ($expectedValues[$key] != $values[$key])
                throw new GenericException($key);
        }
    }

    public function areArrayValuesTheSame(array $array1, array $array2)
    {
        sort($array1);
        sort($array2);
        return ($array1 == $array2);
    }
}
