<?php

namespace StripeIntegration\Payments\Cron;

use Magento\Framework\App\ResourceConnection;

class CleanupDBTables
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * Constructor
     *
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        ResourceConnection $resourceConnection
    ) {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Execute method
     */
    public function execute()
    {
        $connection = $this->resourceConnection->getConnection();
        $webhookEventsTable = $this->resourceConnection->getTableName('stripe_webhook_events');
        $paymentIntentsTable = $this->resourceConnection->getTableName('stripe_payment_intents');
        $paymentElementsTable = $this->resourceConnection->getTableName('stripe_payment_elements');
        $checkoutSessionsTable = $this->resourceConnection->getTableName('stripe_checkout_sessions');

        // Calculate date 3 months ago
        $date = new \DateTime();
        $date->modify('-3 months');
        $formattedDate = $date->format('Y-m-d H:i:s');

        // Delete query
        $where = ['created_at < ?' => $formattedDate];
        $connection->delete($webhookEventsTable, $where);
        $connection->delete($paymentIntentsTable, $where);
        $connection->delete($paymentElementsTable, $where);
        $connection->delete($checkoutSessionsTable, $where);
    }
}
