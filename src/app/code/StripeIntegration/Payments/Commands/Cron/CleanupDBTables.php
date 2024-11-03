<?php

namespace StripeIntegration\Payments\Commands\Cron;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanupDBTables extends Command
{
    private $cleanupDBTables;

    public function __construct(
        \StripeIntegration\Payments\Cron\CleanupDBTables $cleanupDBTables
    )
    {
        $this->cleanupDBTables = $cleanupDBTables;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('stripe:cron:cleanup-db-tables');
        $this->setDescription('Deletes old entries from various DB tables, and optimizes the table for performance.');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try
        {
            $this->cleanupDBTables->execute();
            $output->writeln("<info>Database tables cleaned up successfully.</info>");
        }
        catch (\Exception $e)
        {
            $output->writeln("<error>" . $e->getMessage() . "</error>");
            return 1;
        }

        return 0;
    }
}
