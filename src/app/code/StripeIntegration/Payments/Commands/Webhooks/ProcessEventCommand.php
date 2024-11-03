<?php

namespace StripeIntegration\Payments\Commands\Webhooks;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessEventCommand extends Command
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
        $this->setName('stripe:webhooks:process-event');
        $this->setDescription('Process or resend a webhook event which Stripe failed to deliver.');
        $this->addArgument('event_id', \Symfony\Component\Console\Input\InputArgument::REQUIRED);
        $this->addOption("force", 'f', InputOption::VALUE_NONE, 'Force process even if the event was already sent and processed.');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $eventId = $input->getArgument("event_id");
        $force = $input->getOption("force");

        $areaCode = $this->areaCodeFactory->create();
        $areaCode->setAreaCode();

        $webhookEventHelper = $this->webhookEventFactory->create();
        $webhooks = $this->webhooksFactory->create();

        $event = $webhookEventHelper->getEvent($eventId);
        if (empty($event))
        {
            $output->writeln("<error>Event not found or is no longer available because it's aged out of our retention policy.</error>");
            return 1;
        }

        if ($force)
        {
            $processMoreThanOnce = true;
        }
        else
        {
            $processMoreThanOnce = false;
        }

        $output->writeln(">>> Processing event $eventId");
        $webhooks->setOutput($output);
        $webhooks->dispatchEvent($event, $processMoreThanOnce);

        return 0;
    }
}
