<?php

namespace StripeIntegration\Payments\Model\Stripe\Service;

use StripeIntegration\Payments\Exception\GenericException;

class StripeObjectService
{
    public $lastError = null;
    public $expandParams = [];

    private $objectSpace = null;
    private ?\Stripe\StripeObject $object = null;
    private $config;
    private $requestCache;
    private $compare;

    public function __construct(
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Helper\RequestCache $requestCache,
        \StripeIntegration\Payments\Helper\Compare $compare
    )
    {
        $this->config = $config;
        $this->requestCache = $requestCache;
        $this->compare = $compare;
    }

    public function setObjectSpace($objectSpace)
    {
        $this->objectSpace = $objectSpace;

        return $this;
    }

    public function getStripeObject()
    {
        return $this->object;
    }

    public function setExpandParams($params)
    {
        $this->expandParams = $params;
    }

    public function getType()
    {
        return $this->objectSpace;
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    public function createObject($data)
    {
        $this->object = $this->objectSpace()->create($data);

        if (!empty($this->expandParams))
            $this->object = $this->objectSpace()->retrieve($this->object->id, ['expand' => $this->expandParams]);

        $this->cacheObject();
        return $this->object;
    }

    public function lookupSingle($key)
    {
        $cacheKey = $this->objectSpace . "_" . $key;
        $item = $this->requestCache->get($cacheKey);
        if ($item)
        {
            $this->object = $item;
            return $item;
        }

        $items = $this->objectSpace()->all(['lookup_keys' => [$key], 'limit' => 1]);
        $this->object = $items->first();
        $this->requestCache->set($cacheKey, $this->object);
        return $this->object;
    }

    public function destroy()
    {
        if (!$this->object || empty($this->object->id))
            return;

        $this->objectSpace()->delete($this->object->id, []);
    }

    public function objectSpace()
    {
        $client = $this->config->getStripeClient();

        if (strpos($this->objectSpace, ".") !== false)
        {
            $parts = explode(".", $this->objectSpace);
            foreach ($parts as $part)
                $client = $client->{$part};

            return $client;
        }
        else
        {
            return $client->{$this->objectSpace};
        }
    }

    public function getId()
    {
        if (empty($this->object->id))
            return null;

        return $this->object->id;
    }

    public function load($id)
    {
        $this->object = $this->getObject($id);
    }

    public function getStripeUrl()
    {
        if (empty($this->object))
            return null;

        if ($this->object->livemode)
            return "https://dashboard.stripe.com/{$this->objectSpace}/{$this->object->id}";
        else
            return "https://dashboard.stripe.com/test/{$this->objectSpace}/{$this->object->id}";
    }

    public function upsert($id, $data)
    {
        $this->object = $this->getObject($id);

        if (!$this->object)
        {
            if (!empty($id))
            {
                $data["id"] = $id;
            }

            return $this->createObject($data);
        }
        else
            return $this->updateObject($id, $data);
    }

    public function getObject($id)
    {
        if (empty($id))
            return $this->object = null;

        $key = $this->objectSpace . "_" . $id;
        $this->object = $this->requestCache->get($key);

        if (empty($this->object))
        {
            try
            {
                $this->object = $this->objectSpace()->retrieve($id, ['expand' => $this->expandParams]);
            }
            catch (\Exception $e)
            {
                // The object has not been created yet
                return $this->object = null;
            }

            $this->cacheObject();
        }

        return $this->object;
    }

    public function setObject($object)
    {
        if (empty($object))
            throw new GenericException("Invalid Stripe object specified");

        $this->object = $object;
        $this->cacheObject();
    }

    public function unsetObject()
    {
        $this->object = null;
    }

    private function updateObject($id, $data)
    {
        if ($this->compare->isDifferent($this->object, $data))
        {
            $this->object = $this->objectSpace()->update($id, $data);
            $this->cacheObject();
        }

        return $this->object;
    }

    private function cacheObject()
    {
        $key = $this->objectSpace . "_" . $this->object->id;
        $this->requestCache->set($key, $this->object);
    }

    public function reset()
    {
        if ($this->object)
        {
            $key = $this->objectSpace . "_" . $this->object->id;
            $this->requestCache->delete($key);
        }
        $this->object = null;
    }
}
