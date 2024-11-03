<?php

namespace StripeIntegration\Payments\Commands\Webhooks;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AutomaticConfigurationCommand extends Command
{
    private $resourceConfig;
    private $areaCodeFactory;

    public function __construct(
        \Magento\Config\Model\ResourceModel\Config $resourceConfig,
        \StripeIntegration\Payments\Helper\AreaCodeFactory $areaCodeFactory
    )
    {
        $this->resourceConfig = $resourceConfig;
        $this->areaCodeFactory = $areaCodeFactory;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('stripe:webhooks:automatic-configuration');
        $this->setDescription('Enable or disable automatic webhooks configuration.');
        $this->addArgument('enabled', \Symfony\Component\Console\Input\InputArgument::REQUIRED);
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $enabled = $input->getArgument("enabled");

        $areaCode = $this->areaCodeFactory->create();
        $areaCode->setAreaCode();

        if ($enabled)
        {
            $this->resourceConfig->saveConfig("stripe_settings/automatic_webhooks_configuration", 1, 'default', 0);
            $output->writeln("Enabled automatic webhooks configuration.");
        }
        else
        {
            $this->resourceConfig->saveConfig("stripe_settings/automatic_webhooks_configuration", 0, 'default', 0);
            $output->writeln("Disabled automatic webhooks configuration.");
        }

        return 0;
    }
}
