<?php

namespace StripeIntegration\Payments\Commands\Subscriptions;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use StripeIntegration\Payments\Exception\GenericException;

class MigrateSubscriptionPriceCommand extends Command
{
    public $config = null;
    public $fromProduct;
    public $toProduct;
    public $subscriptionSwitch;
    private $fromProductId;
    private $toProductId;
    private $resource;
    private $orderCollectionFactory;
    private $areaCodeFactory;
    private $configFactory;
    private $subscriptionProductFactory;
    private $subscriptionSwitchFactory;
    private $productHelper;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \StripeIntegration\Payments\Helper\AreaCodeFactory $areaCodeFactory,
        \StripeIntegration\Payments\Model\ConfigFactory $configFactory,
        \StripeIntegration\Payments\Model\SubscriptionProductFactory $subscriptionProductFactory,
        \StripeIntegration\Payments\Helper\SubscriptionSwitchFactory $subscriptionSwitchFactory,
        \StripeIntegration\Payments\Helper\Product $productHelper
    )
    {
        $this->resource = $resource;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->areaCodeFactory = $areaCodeFactory;
        $this->configFactory = $configFactory;
        $this->subscriptionProductFactory = $subscriptionProductFactory;
        $this->subscriptionSwitchFactory = $subscriptionSwitchFactory;
        $this->productHelper = $productHelper;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('stripe:subscriptions:migrate-subscription-price');
        $this->setDescription('Switches existing subscriptions from one plan to a new one with different pricing.');
        $this->addArgument('original_product_id', InputArgument::REQUIRED);
        $this->addArgument('new_product_id', InputArgument::REQUIRED); // This can be the same as the original product ID
        $this->addArgument('starting_order_id', InputArgument::OPTIONAL);
        $this->addArgument('ending_order_id', InputArgument::OPTIONAL);
    }

    protected function init($input)
    {
        $areaCode = $this->areaCodeFactory->create();
        $areaCode->setAreaCode();

        $this->config = $this->configFactory->create();
        $this->subscriptionSwitch = $this->subscriptionSwitchFactory->create();

        $this->fromProductId = $input->getArgument("original_product_id");
        $this->toProductId = $input->getArgument("new_product_id");

        $this->fromProduct = $this->productHelper->getProduct($this->fromProductId);
        $this->toProduct = $this->productHelper->getProduct($this->toProductId);

        if (!$this->fromProduct || !$this->fromProduct->getId())
            throw new GenericException("No such product with ID " . $this->fromProductId);

        if (!$this->toProduct || !$this->toProduct->getId())
            throw new GenericException("No such product with ID " . $this->toProductId);

        if (!$this->subscriptionProductFactory->create()->fromProductId($this->fromProduct->getId())->isSubscriptionProduct())
            throw new GenericException($this->fromProduct->getName() . " is not a subscription product");

        if (!$this->subscriptionProductFactory->create()->fromProductId($this->toProduct->getId())->isSubscriptionProduct())
            throw new GenericException($this->toProduct->getName() . " is not a subscription product");

        if ($this->fromProduct->getTypeId() == "virtual" && $this->toProduct->getTypeId() == "simple")
            throw new GenericException("It is not possible to migrate Virtual subscriptions to Simple subscriptions because we don't have a shipping address.");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("Loading ...");

        $this->init($input);

        $orders = $this->getOrders($input);

        foreach ($orders as $order)
        {
            $this->migrateOrder($order, $output);
        }

        return 0;
    }

    protected function migrateOrder($order, $output)
    {
        $this->initStripeFrom($order);

        if (!$this->config->isInitialized())
        {
            $output->writeln("Could not migrate order #" . $order->getIncrementId() . " because Stripe could not be initialized for store " . $order->getStore()->getName());
            return;
        }

        try
        {
            $migrated = $this->subscriptionSwitch->run($order, $this->fromProduct, $this->toProduct);
            if ($migrated)
                $output->writeln("Successfully migrated order #" . $order->getIncrementId());
        }
        catch (\Exception $e)
        {
            $output->writeln("Could not migrate order #" . $order->getIncrementId() . ": " . $e->getMessage());
        }
    }

    public function initStripeFrom($order)
    {
        $mode = $this->config->getConfigData("mode", "basic", $order->getStoreId());
        $this->config->reInitStripe($order->getStoreId(), $order->getOrderCurrencyCode(), $mode);
    }

    protected function getOrders($input)
    {
        $orderCollection = $this->orderCollectionFactory->create();

        $fromOrderId = $input->getArgument('starting_order_id');
        $toOrderId = $input->getArgument('ending_order_id');

        if (!empty($fromOrderId) && !is_numeric($fromOrderId))
            throw new GenericException("Error: starting_order_id is not a number");

        if (!empty($toOrderId) && !is_numeric($toOrderId))
            throw new GenericException("Error: ending_order_id is not a number");

        if (!empty($fromOrderId))
            $orderCollection->addAttributeToFilter('entity_id', ['gteq' => (int)$fromOrderId]);

        if (!empty($toOrderId))
            $orderCollection->addAttributeToFilter('entity_id', ['lteq' => (int)$toOrderId]);

        $orderCollection->addAttributeToSelect('*')
            ->getSelect()
            ->join(
                ['payment' => $this->resource->getTableName('sales_order_payment')],
                "payment.parent_id = main_table.entity_id",
                []
            )
            ->where("payment.method IN ('stripe_payments', 'stripe_payments_express', 'stripe_payments_checkout')");

        $orders = $orderCollection;

        if ($orders->count() == 0)
            throw new GenericException("Could not find any orders to process");

        return $orders;
    }
}
