<?php

namespace StripeIntegration\Payments\Test\Integration\Helper;

use StripeIntegration\Tax\Test\Integration\Helper\AbstractCompare;

class CreditMemoCompare extends AbstractCompare
{
    public function __construct($test)
    {
        parent::__construct($test);
    }
    public function compareCreditmemoData($creditmemo, $calculatedData, $entity = 'Creditmemo')
    {
        $this->compareGeneralData($creditmemo, $calculatedData, $entity);
        $this->getTest()->assertNotNull($creditmemo->getStripeTaxTransactionId());
    }

    public function compareCreditmemoItemData($quoteItem, $calculatedData, $entity = 'Creditmemo')
    {
        $this->compareItemData($quoteItem, $calculatedData, $entity);
    }
}