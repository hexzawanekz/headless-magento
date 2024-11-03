<?php

namespace StripeIntegration\Payments\Commands\Cron;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use StripeIntegration\Payments\Exception\GenericException;

class CancelAbandonedPaymentsCommand extends Command
{
    private $areaCodeFactory;
    private $webhooksPingFactory;

    public function __construct(
        \StripeIntegration\Payments\Helper\AreaCodeFactory $areaCodeFactory,
        \StripeIntegration\Payments\Cron\WebhooksPingFactory $webhooksPingFactory
    )
    {
        $this->areaCodeFactory = $areaCodeFactory;
        $this->webhooksPingFactory = $webhooksPingFactory;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('stripe:cron:cancel-abandoned-payments');
        $this->setDescription('Cancels pending Magento orders and incomplete payments of a specific age.');
        $this->addArgument('min_age_minutes', \Symfony\Component\Console\Input\InputArgument::REQUIRED);
        $this->addArgument('max_age_minutes', \Symfony\Component\Console\Input\InputArgument::REQUIRED);
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new \Symfony\Component\Console\Style\SymfonyStyle($input, $output);
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $areaCode = $this->areaCodeFactory->create();
        $areaCode->setAreaCode();

        $cron = $this->webhooksPingFactory->create();
        $minAgeMinutes = $input->getArgument("min_age_minutes");
        $maxAgeMinutes = $input->getArgument("max_age_minutes");

        if (!is_numeric($minAgeMinutes) || $minAgeMinutes < 0)
        {
            throw new GenericException("Invalid minimum age.");
        }

        if ($minAgeMinutes < (2*60))
        {
            throw new GenericException("Minimum age must be at least 2 hours.");
        }

        if (!is_numeric($maxAgeMinutes))
        {
            throw new GenericException("Invalid maximum age.");
        }

        if ($maxAgeMinutes <= (2*60))
        {
            throw new GenericException("Maximum age must be larger than 2 hours.");
        }

        $cron->cancelAbandonedPayments($minAgeMinutes, $maxAgeMinutes, $output);

        return 0;
    }
}
