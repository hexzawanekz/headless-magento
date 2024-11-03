<?php

namespace StripeIntegration\Payments\Commands\Webhooks;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessEventsRangeCommand extends Command
{
    private $areaCodeFactory;
    private $webhookEventFactory;
    private $webhooksFactory;

    public function __construct(
        \StripeIntegration\Payments\Helper\AreaCodeFactory $areaCodeFactory,
        \StripeIntegration\Payments\Helper\WebhookEventFactory $webhookEventFactory,
        \StripeIntegration\Payments\Helper\WebhooksFactory $webhooksFactory
    )
    {
        $this->areaCodeFactory = $areaCodeFactory;
        $this->webhookEventFactory = $webhookEventFactory;
        $this->webhooksFactory = $webhooksFactory;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('stripe:webhooks:process-events-range');
        $this->setDescription('Process or resend a range of webhook events.');
        $this->addArgument('from_event_id', \Symfony\Component\Console\Input\InputArgument::REQUIRED);
        $this->addArgument('to_event_id', \Symfony\Component\Console\Input\InputArgument::REQUIRED);
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fromEventId = $input->getArgument("from_event_id");
        $toEventId = $input->getArgument("to_event_id");

        $areaCode = $this->areaCodeFactory->create();
        $areaCode->setAreaCode();

        $webhookEventHelper = $this->webhookEventFactory->create();
        $webhooks = $this->webhooksFactory->create();

        $fromEvent = $webhookEventHelper->getEvent($fromEventId);
        $toEvent = $webhookEventHelper->getEvent($toEventId);
        if (empty($fromEvent))
        {
            $output->writeln("<error>Event with ID $fromEventId not found.</error>");
            return 1;
        }
        if (empty($toEvent))
        {
            $output->writeln("<error>Event with ID $toEvent not found.</error>");
            return 1;
        }

        $webhooks->setOutput($output);
        $events = $webhookEventHelper->getEventRange($fromEvent->created, $toEvent->created);

        if (!empty($events))
        {
            foreach ($events->autoPagingIterator() as $event)
            {
                $output->writeln(">>> Processing event {$event->id}");
                $webhooks->dispatchEvent($event);
            }
        }

        return 0;
    }
}
