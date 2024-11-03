<?php

namespace StripeIntegration\Payments\Commands\Webhooks;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanupCommand extends Command
{
    private $areaCodeFactory;
    private $webhooksSetupFactory;
    private $configFactory;

    public function __construct(
        \StripeIntegration\Payments\Helper\AreaCodeFactory $areaCodeFactory,
        \StripeIntegration\Payments\Helper\WebhooksSetupFactory $webhooksSetupFactory,
        \StripeIntegration\Payments\Model\ConfigFactory $configFactory
    )
    {
        $this->areaCodeFactory = $areaCodeFactory;
        $this->webhooksSetupFactory = $webhooksSetupFactory;
        $this->configFactory = $configFactory;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('stripe:webhooks:cleanup');
        $this->setDescription('Removes products named "Webhook Ping"');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new \Symfony\Component\Console\Style\SymfonyStyle($input, $output);

        $areaCode = $this->areaCodeFactory->create();
        $areaCode->setAreaCode();

        $webhooksSetup = $this->webhooksSetupFactory->create();
        $config = $this->configFactory->create();
        $keys = $webhooksSetup->getAllActiveAPIKeys();

        foreach ($keys as $secretKey => $publishableKey)
        {
            $config->initStripeFromSecretKey($secretKey);
            $stripe = $config->getStripeClient();
            $products = $stripe->products->all(['limit' => 100]);
            $io->progressStart($products->count());
            try
            {
                foreach ($products->autoPagingIterator() as $product)
                {
                    if ($product->name == "Webhook Ping")
                    {
                        $product->delete();
                    }
                    $io->progressAdvance();
                }
            }
            catch (\Exception $e)
            {
                $io->note($e->getMessage());
            }
            $io->progressFinish();
        }

        return 0;
    }
}
