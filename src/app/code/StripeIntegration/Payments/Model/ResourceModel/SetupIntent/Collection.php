<?php

namespace StripeIntegration\Payments\Model\ResourceModel\SetupIntent;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected $_idFieldName = 'si_id';

    protected function _construct()
    {
        $this->_init('StripeIntegration\Payments\Model\SetupIntent', 'StripeIntegration\Payments\Model\ResourceModel\SetupIntent');
    }

    public function findByQuoteId($quoteId)
    {
        if (empty($quoteId) || !is_numeric($quoteId))
            return;

        $this->clear()->getSelect()->reset(\Magento\Framework\DB\Select::WHERE);

        $collection = $this->addFieldToSelect('*')
                    ->addFieldToFilter('quote_id', ['eq' => $quoteId]);

        return $collection->getFirstItem();
    }

    public function findBySetupIntentId($siId)
    {
        if (empty($siId))
            return;

        $this->clear()->getSelect()->reset(\Magento\Framework\DB\Select::WHERE);

        $collection = $this->addFieldToSelect('*')
                    ->addFieldToFilter('si_id', ['eq' => $siId]);

        return $collection->getFirstItem();
    }
}
