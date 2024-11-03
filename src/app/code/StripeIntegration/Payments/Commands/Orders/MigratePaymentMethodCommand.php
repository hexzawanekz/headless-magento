<?php

namespace StripeIntegration\Payments\Commands\Orders;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigratePaymentMethodCommand extends Command
{
    private $migrate;
    private $migrateFactory;

    public function __construct(
        \StripeIntegration\Payments\Helper\MigrateFactory $migrateFactory
    ) {
        $this->migrateFactory = $migrateFactory;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('stripe:orders:migrate-payment-method');
        $this->setDescription('Migrates the payment method for orders placed by other Stripe modules');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->migrate = $this->migrateFactory->create();
        $this->migrate->orders();

        return 0;
    }
}
