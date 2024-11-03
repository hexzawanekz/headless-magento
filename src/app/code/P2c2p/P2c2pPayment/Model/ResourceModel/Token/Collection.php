<?php

namespace P2c2p\P2c2pPayment\Model\ResourceModel\Token;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
	protected function _construct()
	{
		$this->_init('P2c2p\P2c2pPayment\Model\Token', 'P2c2p\P2c2pPayment\Model\ResourceModel\Token');
	}
}