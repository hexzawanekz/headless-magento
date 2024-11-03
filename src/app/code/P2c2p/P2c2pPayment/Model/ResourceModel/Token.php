<?php
namespace P2c2p\P2c2pPayment\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Token extends AbstractDb
{
	protected function _construct()
	{
		$this->_init('p2c2p_token', 'p2c2p_id');		
	}
}