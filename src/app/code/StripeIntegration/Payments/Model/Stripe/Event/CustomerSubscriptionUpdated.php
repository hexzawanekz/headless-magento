<?php

namespace StripeIntegration\Payments\Model\Stripe\Event;

use StripeIntegration\Payments\Exception\WebhookException;
use StripeIntegration\Payments\Model\Stripe\StripeObjectTrait;

class CustomerSubscriptionUpdated
{
    use StripeObjectTrait;

    private $webhooksHelper;
    private $subscriptionsHelper;
    private $config;

    public function __construct(
        \StripeIntegration\Payments\Model\Stripe\Service\StripeObjectServicePool $stripeObjectServicePool,
        \StripeIntegration\Payments\Helper\Webhooks $webhooksHelper,
        \StripeIntegration\Payments\Helper\Subscriptions $subscriptionsHelper,
        \StripeIntegration\Payments\Model\Config $config
    )
    {
        $stripeObjectService = $stripeObjectServicePool->getStripeObjectService('events');
        $this->setData($stripeObjectService);

        $this->webhooksHelper = $webhooksHelper;
        $this->subscriptionsHelper = $subscriptionsHelper;
        $this->config = $config;
    }

    public function process($arrEvent, $object, $stdEvent)
    {
        $subscription = $stdEvent->data->object;

        try
        {
            $order = $this->webhooksHelper->loadOrderFromEvent($arrEvent);
        }
        catch (WebhookException $e)
        {
            if ($e->statusCode == 202 && isset($object['metadata']['Original Order #']))
            {
                // This is a subscription update which did not generate a new order.
                // Orders are not generated when there is no payment collected
                // So there is nothing to do here, skip the case
                return;
            }
            else
            {
                throw $e;
            }
        }

        if (empty($order->getPayment()))
            throw new WebhookException("Order #%1 does not have any associated payment details.", $order->getIncrementId());

        $this->subscriptionsHelper->updateSubscriptionEntry($subscription, $order);

        $invoiceId = $stdEvent->data->object->latest_invoice;

        if (empty($invoiceId))
        {
            // This is a new subscription purchase, not an update
            return;
        }

        $invoiceParams = [
            'expand' => [
                'subscription',
                'payment_intent'
            ]
        ];
        $invoice = $this->config->getStripeClient()->invoices->retrieve($invoiceId, $invoiceParams);

        $this->webhooksHelper->setPaymentDescriptionAfterSubscriptionUpdate($order, $invoice);
    }
}
