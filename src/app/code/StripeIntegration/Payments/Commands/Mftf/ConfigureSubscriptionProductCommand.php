<?php

namespace StripeIntegration\Payments\Commands\Mftf;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class ConfigureSubscriptionProductCommand extends Command
{
    private $areaCodeFactory;
    private $subscriptionOptionsFactory;
    private $subscriptionOptionsResourceModelFactory;

    public function __construct(
        \StripeIntegration\Payments\Helper\AreaCodeFactory $areaCodeFactory,
        \StripeIntegration\Payments\Model\SubscriptionOptionsFactory $subscriptionOptionsFactory,
        \StripeIntegration\Payments\Model\ResourceModel\SubscriptionOptionsFactory $subscriptionOptionsResourceModelFactory
    )
    {
        $this->areaCodeFactory = $areaCodeFactory;
        $this->subscriptionOptionsFactory = $subscriptionOptionsFactory;
        $this->subscriptionOptionsResourceModelFactory = $subscriptionOptionsResourceModelFactory;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('stripe:mftf:configure-subscription-product');
        $this->setDescription('Used by the MFTF tests to set extension attributes on subscription products. This command is not meant to be used directly.');
        $this->addArgument('product_id', InputArgument::REQUIRED);
        $this->addArgument('configuration', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $areaCode = $this->areaCodeFactory->create();
        $areaCode->setAreaCode();

        $productId = $input->getArgument("product_id");
        $configuration = $input->getArgument("configuration");

        $subscriptionOptions = $this->subscriptionOptionsFactory->create();
        $subscriptionOptionsResourceModel = $this->subscriptionOptionsResourceModelFactory->create();
        $subscriptionOptionsResourceModel->load($subscriptionOptions, $productId, "product_id");

        // Set default values
        $subscriptionOptions->setProductId($productId);
        $subscriptionOptions->setSubEnabled(true);
        $subscriptionOptions->setSubInterval("month");
        $subscriptionOptions->setSubIntervalCount(1);
        $subscriptionOptions->setSubTrial(0);
        $subscriptionOptions->setSubInitialFee(0);
        $subscriptionOptions->setStartOnSpecificDate(false);
        $subscriptionOptions->setFirstPayment("on_start_date");
        $subscriptionOptions->setProrateFirstPayment(false);
        $subscriptionOptions->setUpgradesDowngrades(true);
        $subscriptionOptions->setUpgradesDowngradesUseConfig(1);
        $subscriptionOptions->setProrateUpgrades(false);
        $subscriptionOptions->setProrateUpgradesUseConfig(1);
        $subscriptionOptions->setProrateDowngrades(false);
        $subscriptionOptions->setProrateDowngradesUseConfig(1);

        if ($configuration == "SimpleMonthlySubscription")
        {
            $subscriptionOptionsResourceModel->save($subscriptionOptions);
        }
        else if ($configuration == "SimpleTrialMonthlySubscription")
        {
            $subscriptionOptions->setSubTrial(14);
            $subscriptionOptionsResourceModel->save($subscriptionOptions);
        }
        else if ($configuration == "SimpleQuarterlySubscription")
        {
            $subscriptionOptions->setSubIntervalCount(3);
            $subscriptionOptionsResourceModel->save($subscriptionOptions);
        }

        return 0;
    }
}
