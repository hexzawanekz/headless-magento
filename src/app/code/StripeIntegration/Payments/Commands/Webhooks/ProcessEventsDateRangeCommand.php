<?php

namespace StripeIntegration\Payments\Commands\Webhooks;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessEventsDateRangeCommand extends Command
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
        $this->setName('stripe:webhooks:process-events-date-range');
        $this->setDescription('Process or resend webhook events that were triggered between two dates.');
        $this->addArgument('from_date', \Symfony\Component\Console\Input\InputArgument::REQUIRED);
        $this->addArgument('to_date', \Symfony\Component\Console\Input\InputArgument::OPTIONAL);
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new \Symfony\Component\Console\Style\SymfonyStyle($input, $output);
        $fromDate = strtotime($input->getArgument("from_date"));
        $fromDateReadable = date('jS F Y h:i:s A', $fromDate);
        if ($input->getArgument("to_date"))
        {
            $toDate = strtotime($input->getArgument("to_date"));
        }
        else
        {
            $toDate = time();
        }

        $toDateReadable = date('jS F Y h:i:s A', $toDate);

        $areaCode = $this->areaCodeFactory->create();
        $areaCode->setAreaCode();

        $webhookEventHelper = $this->webhookEventFactory->create();
        $webhooks = $this->webhooksFactory->create();
        $webhooks->setOutput($output);
        $webhookEventHelper->initStripeClientForStore($io);
        $events = $webhookEventHelper->getEventRange($fromDate, $toDate);

        if (!empty($events))
        {
            $count = $events->count();
            $output->writeln(">>> Fount $count events between $fromDateReadable - $toDateReadable");
            foreach ($events->autoPagingIterator() as $event)
            {
                $output->writeln(">>> Processing event {$event->id}");
                $webhooks->dispatchEvent($event);
            }
        }
        else
        {
            $output->writeln(">>> No events found between $fromDateReadable - $toDateReadable");
        }

        return 0;
    }
}
