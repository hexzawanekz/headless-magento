<?php
 
 /*
 * Created by 2C2P
 * Date 28 June 2017
 * Create P2c2p require table in database when plugin/module is installed in Magento-2
 */

namespace P2c2p\P2c2pPayment\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

class InstallSchema implements InstallSchemaInterface
{
	public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
	{
		$installer = $setup;

		/**
         * Prepare database for install
         */
		$installer->startSetup();

        try {
          // Required tables
          $statusTable = $installer->getTable('sales_order_status');
          $statusStateTable = $installer->getTable('sales_order_status_state');

          // Insert statuses
          $installer->getConnection()->insertArray(
            $statusTable,
            array('status','label'),
            array(array('status' => 'Pending_2C2P', 'label' => 'Pending 2C2P'))
            );

          // Insert states and mapping of statuses to states
          $installer->getConnection()->insertArray(
            $statusStateTable,
            array(
              'status',
              'state',
              'is_default',
              'visible_on_front'
              ),
            array(
              array(
                'status' => 'Pending_2C2P',
                'state' => 'Pending_2C2P',
                'is_default' => 0,
                'visible_on_front' => 1
                )
              )
            );
      } catch (Exception $e) {}

		/**
		* Create p2c2p_token table
		*/
        if(!$installer->tableExists('p2c2p/token')) {

    		$table = $installer->getConnection()->newTable(
                $installer->getTable('p2c2p_token')
            )->addColumn(
                'p2c2p_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true]            
            )->addColumn(
                'user_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                10,
                ['unsigned' => true, 'nullable' => false]            
            )->addColumn(
                'stored_card_unique_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => false]            
            )->addColumn(
                'masked_pan',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => false]            
            )->addColumn(
                'created_time',
                \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT]            
            )->addIndex(
                $installer->getIdxName('p2c2p_token', ['p2c2p_id']),
                ['p2c2p_id']
            )->addForeignKey(
                $installer->getFkName('p2c2p_token', 'user_id', 'customer_entity', 'entity_id'),
                'user_id',
                $installer->getTable('customer_entity'),
                'entity_id',
                \Magento\Framework\DB\Ddl\Table::ACTION_CASCADE
            );

            $installer->getConnection()->createTable($table);
        }

        /**
		* Create p2c2p_meta table.
		*/
        if(!$installer->tableExists('p2c2p/meta')) {
            
            $table = $installer->getConnection()->newTable(
                $installer->getTable('p2c2p_meta')
            )->addColumn(
                'p2c2p_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true]            
            )->addColumn(
                'order_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                20,
                ['unsigned' => true, 'nullable' => false]            
            )->addColumn(
                'user_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['nullable' => false]            
            )->addColumn(
                'version',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                5,
                ['nullable' => false]            
            )->addColumn(
                'request_timestamp',
                \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT]
            )->addColumn(
                'merchant_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                25,
                ['nullable' => false]
            )->addColumn(
                'invoice_no',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                50,
                ['nullable' => true]
            )->addColumn(
                'currency',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                3,
                ['nullable' => true]
            )->addColumn(
                'amount',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                20,
                ['nullable' => true]
            )->addColumn(
                'transaction_ref',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                15,
                ['nullable' => true]
            )->addColumn(
                'approval_code',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                6,
                ['nullable' => true]
            )->addColumn(
                'eci',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                2,
                ['nullable' => true]
            )->addColumn(
                'transaction_datetime',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                100,
                ['nullable' => true]
            )->addColumn(
                'payment_channel',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                3,
                ['nullable' => true]
            )->addColumn(
                'payment_status',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                3,
                ['nullable' => true]
            )->addColumn(
                'channel_response_code',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                2,
                ['nullable' => true]
            )->addColumn(
                'channel_response_desc',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => true]
            )->addColumn(
                'masked_pan',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                16,
                ['nullable' => true]
            )->addColumn(
                'stored_card_unique_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                20,
                ['nullable' => true]
            )->addColumn(
                'backend_invoice',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                12,
                ['nullable' => true]
            )->addColumn(
                'paid_channel',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                30,
                ['nullable' => true]
            )->addColumn(
                'paid_agent',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                30,
                ['nullable' => true]
            )->addColumn(
                'recurring_unique_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                20,
                ['nullable' => true]
            )->addColumn(
                'user_defined_1',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => true]
            )->addColumn(
                'user_defined_2',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => true]
            )->addColumn(
                'user_defined_3',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => true]
            )->addColumn(
                'user_defined_4',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => true]
            )->addColumn(
                'user_defined_5',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                ['nullable' => true]
            )->addColumn(
                'browser_info',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                50,
                ['nullable' => true]
            )->addColumn(
                'ippPeriod',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                2,
                ['nullable' => true]
            )->addColumn(
                'ippInterestType',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                1,
                ['nullable' => true]
            )->addColumn(
                'ippInterestRate',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                5,
                ['nullable' => true]
            )->addColumn(
                'ippMerchantAbsorbRate',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                5,
                ['nullable' => true]
            )->addIndex(
                $installer->getIdxName('p2c2p_meta', ['p2c2p_id']),
                ['p2c2p_id']
            );

            $installer->getConnection()->createTable($table);
        }

		$installer->endSetup();
	}
}

?>