<?php

namespace StripeIntegration\Payments\Cron;

class RetryFailedWebhookEvents
{
    private $helper;
    private $webhookEventCollectionFactory;

    public function __construct(
        \StripeIntegration\Payments\Model\ResourceModel\WebhookEvent\CollectionFactory $webhookEventCollectionFactory,
        \StripeIntegration\Payments\Helper\Generic $helper
    ) {
        $this->helper = $helper;
        $this->webhookEventCollectionFactory = $webhookEventCollectionFactory;
    }

    public function execute()
    {
        $webhookEventCollection = $this->webhookEventCollectionFactory->create()->getFailedEvents();

        foreach ($webhookEventCollection as $webhookEventModel)
        {
            if (!$webhookEventModel->shouldRetry())
            {
                return false;
            }

            try
            {
                $webhookEventModel->setRetries($webhookEventModel->getRetries() + 1)->save();
                $webhookEventModel->process();
            }
            catch (\Exception $e)
            {
                $webhookEventModel->setLastErrorFromException($e);
                $this->helper->logError("Could not process webhook event " . $webhookEventModel->getEventId() . ": " . $e->getMessage(), $e->getTraceAsString());
            }
        }
    }
}
