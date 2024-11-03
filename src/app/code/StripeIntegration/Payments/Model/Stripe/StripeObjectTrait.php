<?php

namespace StripeIntegration\Payments\Model\Stripe;

use StripeIntegration\Payments\Model\Stripe\Service\StripeObjectService;

trait StripeObjectTrait
{
    /** @var StripeObjectService */
    private $stripeObjectService;

    public function setData($stripeObjectService)
    {
        $this->stripeObjectService = $stripeObjectService;
    }

    public function getId()
    {
        return $this->stripeObjectService->getId();
    }

    public function load($id)
    {
        $this->stripeObjectService->load($id);

        return $this;
    }

    public function getStripeObject()
    {
        return $this->stripeObjectService->getStripeObject();
    }

    public function getObject($id)
    {
        return $this->stripeObjectService->getObject($id);
    }

    public function createObject($data)
    {
        return $this->stripeObjectService->createObject($data);
    }

    public function getLastError()
    {
        return $this->stripeObjectService->getLastError();
    }

    public function setExpandParams($params)
    {
        $this->stripeObjectService->setExpandParams($params);

        return $this;
    }

    public function setObject($object)
    {
        $this->stripeObjectService->setObject($object);

        return $this;
    }

    public function unsetObject()
    {
        $this->stripeObjectService->unsetObject();

        return $this;
    }

    public function lookupSingle($key)
    {
        return $this->stripeObjectService->lookupSingle($key);
    }

    public function upsert($id, $data)
    {
        return $this->stripeObjectService->upsert($id, $data);
    }

    public function getStripeUrl()
    {
        return $this->stripeObjectService->getStripeUrl();
    }

    public function getType()
    {
        return $this->stripeObjectService->getType();
    }

    public function destroy()
    {
        $this->stripeObjectService->destroy();
    }

    public function objectSpace()
    {
        return $this->stripeObjectService->objectSpace();
    }

    public function reset()
    {
        $this->stripeObjectService->reset();
    }
}