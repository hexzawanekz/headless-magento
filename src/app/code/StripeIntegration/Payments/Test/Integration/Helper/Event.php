<?php

namespace StripeIntegration\Payments\Test\Integration\Helper;

class Event
{
    protected static $eventID;
    protected $tests = null;
    public $stripeConfig;
    public $objectManager;
    public $objectCollection;
    private $eventType;
    private $request;
    private $response;
    private $webhooks;
    private $logger;

    public function __construct($tests, $type = null)
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->tests = $tests;

        if (empty($this::$eventID))
            $this::$eventID = time();

        $this->stripeConfig = $this->objectManager->get(\StripeIntegration\Payments\Model\Config::class);
        $this->request = $this->objectManager->get(\Magento\Framework\App\Request\Http::class);
        $this->response = $this->objectManager->get(\Magento\Framework\App\Response\Http::class);
        $this->webhooks = $this->objectManager->get(\StripeIntegration\Payments\Helper\Webhooks::class);
        $this->logger = $this->objectManager->get(\StripeIntegration\Payments\Helper\Logger::class);

        if ($type)
            $this->setType($type);
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function setType($type)
    {
        switch (true)
        {
            case (strpos($type, "customer.subscription.") === 0):
                $this->objectCollection = "subscriptions";
                break;

            case (strpos($type, "charge.") === 0):
                $this->objectCollection = "charges";
                break;

            case (strpos($type, "review.") === 0):
                $this->objectCollection = "reviews";
                break;

            case (strpos($type, "payment_intent.") === 0):
                $this->objectCollection = "paymentIntents";
                break;

            case (strpos($type, "invoice.") === 0):
                $this->objectCollection = "invoices";
                break;

            case (strpos($type, "checkout.session.") === 0):
                $this->objectCollection = "checkout.sessions";
                break;

            case (strpos($type, "setup_intent.") === 0):
                $this->objectCollection = "setupIntents";
                break;

            default:
                throw new \Exception("Event type $type is not supported");
        }

        $this->eventType = $type;

        return $this;
    }

    public function getObject($objectId)
    {
        switch ($this->objectCollection)
        {
            case "checkout.sessions":
                return $this->stripeConfig->getStripeClient()->checkout->sessions->retrieve($objectId);
            default:
                return $this->stripeConfig->getStripeClient()->{$this->objectCollection}->retrieve($objectId);
        }
    }

    public function getObjectData($object, $extraParams = [])
    {
        $data = null;

        if (is_string($object))
        {
            $data = $this->getObject($object);
        }
        else if (is_object($object) || is_array($object))
        {
            $data = $object;
        }

        if (!empty($extraParams))
        {
            $data = json_decode(json_encode($data), true);
            $data = array_merge($data, $extraParams);
        }

        return json_encode($data);
    }

    public function getEventPayload($object, $extraParams = [])
    {
        return '{
  "id": "'. $this->getEventId() .'",
  "object": "event",
  "api_version": "2020-08-27",
  "created": 1627988871,
  "data": {
    "object": '.$this->getObjectData($object, $extraParams).'
  },
  "livemode": false,
  "pending_webhooks": 1,
  "request": {
    "id": "req_BKKckAZxOJfuGB",
    "idempotency_key": null
  },
  "type": "'.$this->eventType.'"
}';
    }

    public function dispatch($object, $extraParams = [])
    {
        $payload = $this->getEventPayload($object, $extraParams);
        $this->request->setMethod("POST");
        $this->request->setContent($payload);
        $this->webhooks->dispatchEvent();
    }

    public function dispatchEvent($event, $extraParams = [])
    {
        $this->request->setMethod("POST");
        $this->request->setContent(json_encode($event));
        $this->webhooks->dispatchEvent();
    }
    protected function getEventId()
    {
        return 'evt_xxx_' . $this::$eventID++;
    }

    public function getInvoiceFromSubscription($subscription)
    {
        if ($subscription->billing_cycle_anchor > time() && empty($subscription->latest_invoice))
        {
            return null;
        }

        if (is_object($subscription->latest_invoice))
        {
            if (is_object($subscription->latest_invoice->charge))
                return $subscription->latest_invoice;
            else
            {
                $invoiceId = $subscription->latest_invoice->id;
            }
        }
        else
            $invoiceId = $subscription->latest_invoice;

        $wait = 3;
        do
        {
            try
            {
                return $this->stripeConfig->getStripeClient()->invoices->retrieve($invoiceId, ['expand' => ['charge']]);
            }
            catch (\Stripe\Exception\ApiErrorException $e)
            {
                // $e is: This object cannot be accessed right now because another API request or Stripe process is currently accessing it.
                $wait--;
                if ($wait < 0)
                    throw $e;
            }
        }
        while ($wait > 0);
    }

    public function triggerSubscriptionEvents($subscription)
    {
        $this->tests->assertNotEmpty($subscription, 'The subscription was not created');
        if ($subscription->billing_cycle_anchor <= time())
        {
            $this->tests->assertNotEmpty($subscription->latest_invoice);
        }

        $invoice = $this->getInvoiceFromSubscription($subscription);

        $this->triggerEvent("customer.subscription.created", $subscription);

        if ($invoice)
        {
            if ($invoice->charge)
                $this->triggerPaymentIntentEvents($invoice->payment_intent);

            $this->triggerEvent('invoice.payment_succeeded', $invoice);
        }

        $wait = 6;
        while (empty($subscription->default_payment_method) && $wait > 0)
        {
            sleep(1);
            $subscription = $this->stripeConfig->getStripeClient()->subscriptions->retrieve($subscription->id);
            $wait--;
        }
    }

    public function triggerSubscriptionEventsById($subscriptionId)
    {
        $this->tests->assertNotEmpty($subscriptionId, 'No subscription ID passed');

        /** @var \Stripe\StripeObject $subscription */
        $subscription = $this->stripeConfig->getStripeClient()->subscriptions->retrieve($subscriptionId, [
            'expand' => ['latest_invoice', 'default_payment_method']]
        );

        $customerId = !empty($subscription->customer) ? $subscription->customer : null;
        $paymentMethodId = !empty($subscription->default_payment_method->id) ? $subscription->default_payment_method->id : null;

        $setupIntentParams = [
            'limit' => 3
        ];
        if ($customerId)
            $setupIntentParams['customer'] = $customerId;
        if ($paymentMethodId)
            $setupIntentParams['payment_method'] = $paymentMethodId;
        $setupIntent = null;

        $setupIntents = $this->stripeConfig->getStripeClient()->setupIntents->all($setupIntentParams);
        foreach ($setupIntents as $si)
        {
            if ($si->status == 'succeeded')
            {
                $setupIntent = $si;
                break;
            }
        }

        if (!empty($subscription->latest_invoice->charge))
        {
            /** @var \Stripe\StripeObject $subscription */
            $this->triggerPaymentIntentEvents($subscription->latest_invoice->payment_intent);
        }
        $this->trigger("customer.subscription.created", $subscription->id);
        if ($setupIntent)
        {
            $this->trigger("setup_intent.succeeded", $setupIntent);
        }
        if (!empty($subscription->latest_invoice))
        {
            $this->trigger("invoice.paid", $subscription->latest_invoice);
            $this->trigger("invoice.payment_succeeded", $subscription->latest_invoice);
        }
    }

    public function triggerPaymentIntentEvents($paymentIntent, $test = null)
    {
        if (is_string($paymentIntent))
            $paymentIntent = $this->stripeConfig->getStripeClient()->paymentIntents->retrieve($paymentIntent);

        if (!empty($paymentIntent->charges->data[0]))
            $this->triggerEvent('charge.succeeded', $paymentIntent->charges->data[0]);

        $this->triggerEvent('payment_intent.succeeded', $paymentIntent);

        return $paymentIntent;
    }

    public function triggerEvent($type, $object, $extraParams = [])
    {
        $this->setType($type);
        $this->dispatch($object, $extraParams);
        $this->tests->assertEquals("", $this->getResponse()->getContent());
        $this->tests->assertEquals(200, $this->getResponse()->getStatusCode());
    }

    public function trigger($type, $object, $extraParams = [])
    {
        $this->triggerEvent($type, $object, $extraParams);
    }
}
