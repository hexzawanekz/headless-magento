<?php

namespace StripeIntegration\Payments\Model\Stripe;

use StripeIntegration\Payments\Exception\GenericException;

class WebhookEndpoint
{
    use StripeObjectTrait;

    private $objectSpace = 'webhookEndpoints';
    private $stripeClient = null;
    private $publishableKey = null;
    private $webhookFactory;

    public function __construct(
        \StripeIntegration\Payments\Model\Stripe\Service\StripeObjectServicePool $stripeObjectServicePool,
        \StripeIntegration\Payments\Model\WebhookFactory $webhookFactory
    )
    {
        $stripeObjectService = $stripeObjectServicePool->getStripeObjectService($this->objectSpace);
        $this->setData($stripeObjectService);

        $this->webhookFactory = $webhookFactory;
    }

    private function isInitialized()
    {
        return $this->getId() && $this->stripeClient && !empty($this->publishableKey);
    }

    private function getCreateData($url)
    {
        return [
            'url' => $url,
            'api_version' => \StripeIntegration\Payments\Model\Config::STRIPE_API,
            'connect' => false,
            'enabled_events' => \StripeIntegration\Payments\Helper\WebhooksSetup::$enabledEvents,
        ];
    }

    private function getUpdateData($url = null)
    {
        if (!$this->isInitialized())
        {
            throw new GenericException("Cannot update an uninitialized webhook object");
        }

        return [
            'url' => ($url ? $url : $this->getStripeObject()->url),
            'enabled_events' => \StripeIntegration\Payments\Helper\WebhooksSetup::$enabledEvents,
        ];
    }

    public function fromStripeObject($webhookEndpoint, $stripeClient, $publishableKey)
    {
        $this->setObject($webhookEndpoint);
        $this->stripeClient = $stripeClient;
        $this->publishableKey = $publishableKey;

        return $this;
    }

    public function fromUrl($url, $stripeClient, $publishableKey)
    {
        $this->stripeClient = $stripeClient;
        $this->publishableKey = $publishableKey;

        $data = $this->getCreateData($url);

        $this->setObject($stripeClient->webhookEndpoints->create($data));

        $entry = $this->webhookFactory->create()->load($this->getId(), "webhook_id");
        $entry->addData([
            "config_version" => \StripeIntegration\Payments\Helper\WebhooksSetup::VERSION,
            "webhook_id" => $this->getStripeObject()->id,
            "publishable_key" => $this->publishableKey,
            "live_mode" => $this->getStripeObject()->livemode,
            "api_version" => $this->getStripeObject()->api_version,
            "url" => $this->getStripeObject()->url,
            "enabled_events" => json_encode($this->getStripeObject()->enabled_events),
            "secret" => $this->getStripeObject()->secret
        ]);

        $entry->save();

        $this->activate();

        return $this;
    }

    // Checks if we have a record of this endpoint in the database
    public function isKnown()
    {
        $localRecord = $this->getLocalRecord();

        if (!$localRecord)
        {
            return false;
        }
        else
        {
            return true;
        }
    }

    // Loads the local record from the database for this webhook endpoint
    public function getLocalRecord()
    {
        if (!$this->isInitialized())
        {
            throw new GenericException("Webhook object has not been initialized.");
        }

        $entry = $this->webhookFactory->create()->load($this->getId(), "webhook_id");

        if ($entry && $entry->getId())
        {
            return $entry;
        }

        return null;
    }

    public function canUpdate()
    {
        if (!$this->isKnown())
            return false;

        if ($this->getStripeObject()->api_version != \StripeIntegration\Payments\Model\Config::STRIPE_API)
            return false;

        $localRecord = $this->getLocalRecord();
        if (empty($localRecord->getSecret()))
            return false;

        return true;
    }

    public function update($url = null)
    {
        $localRecord = $this->getLocalRecord();
        $updateData = $this->getUpdateData($url);

        $this->setObject($this->stripeClient->webhookEndpoints->update($this->getId(), $updateData));

        $localRecord->addData([
            "config_version" => \StripeIntegration\Payments\Helper\WebhooksSetup::VERSION,
            "webhook_id" => $this->getStripeObject()->id,
            "publishable_key" => $this->publishableKey,
            "live_mode" => $this->getStripeObject()->livemode,
            "api_version" => $this->getStripeObject()->api_version,
            "url" => $this->getStripeObject()->url,
            "enabled_events" => json_encode($this->getStripeObject()->enabled_events),
        ]);

        $localRecord->save();

        return $this;
    }

    public function destroy()
    {
        $localRecord = $this->getLocalRecord();
        $this->stripeClient->webhookEndpoints->delete($this->getId(), []);
        $localRecord->delete();
        $this->unsetObject();

        return null;
    }

    public function activate()
    {
        $product = $this->stripeClient->products->create([
           'name' => 'Webhook Configuration',
           'type' => 'service',
           'metadata' => [
                "webhook_id" => $this->getId()
           ]
        ]);
        try
        {
            $product->delete();
        }
        catch (\Exception $e) { }
    }

    public function getUrl()
    {
        if (empty($this->getStripeObject()->url))
        {
            throw new GenericException("No url exists on uninitialized webhook object.");
        }

        return $this->getStripeObject()->url;
    }

    public function getName()
    {
        /** @var \Stripe\WebhookEndpoint $stripeObject */
        $stripeObject = $this->getStripeObject();

        if (!empty($stripeObject->url))
        {
            return "{$stripeObject->url} ({$stripeObject->id})";
        }

        return $stripeObject->id;
    }

    public function isDisabled()
    {
        return $this->getStripeObject()->status == "disabled";
    }
}
