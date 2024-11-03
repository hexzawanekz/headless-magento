<?php

namespace StripeIntegration\Payments\Model\Customer;

class NameParser
{
    private $firstName = null;
    private $middleName = null;
    private $lastName = null;

    public function fromString(?string $name)
    {
        if (!is_string($name))
            return $this;

        $name = trim($name);

        if (empty($name))
            return $this;

        $name = preg_replace('!\s+!', ' ', $name); // Replace multiple spaces

        $nameParts = explode(' ', $name);
        $this->firstName = array_shift($nameParts);

        if (empty($this->firstName) || count($nameParts) == 0)
            return $this;

        if (count($nameParts) == 1)
        {
            $this->lastName = $nameParts[0];
        }
        else
        {
            $this->lastName = array_pop($nameParts);
            $this->middleName = implode(" ", $nameParts);
        }

        return $this;
    }

    public function getFirstname()
    {
        return $this->firstName;
    }

    public function getMiddlename()
    {
        return $this->middleName;
    }

    public function getLastname()
    {
        return $this->lastName;
    }
}