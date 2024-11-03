<?php

namespace StripeIntegration\Payments\Test\Mftf\Mock\Model;

use StripeIntegration\Payments\Api\Data\SubscriptionOptionsInterface;

class SubscriptionOptions extends \Magento\Framework\Model\AbstractModel implements SubscriptionOptionsInterface
{
    protected function _construct()
    {
        $this->_init('StripeIntegration\Payments\Model\ResourceModel\SubscriptionOptions');
    }
}
