<?php

namespace StripeIntegration\Payments\Model\ResourceModel;

class SetupIntent extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    protected $_isPkAutoIncrement = false;

    protected function _construct()
    {
        $this->_init('stripe_setup_intents', 'si_id');
    }
}
