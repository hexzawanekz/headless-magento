<?php

namespace StripeIntegration\Tax\Model\StripeTransaction;

class Request
{
    public const CALCULATION_FIELD_NAME = 'calculation';
    public const REFERENCE_FIELD_NAME = 'reference';
    public const METADATA_FIELD_NAME = 'metadata';
    public const EXPAND_FIELD_NAME = 'expand';

    private $calculation;
    private $reference;
    private $metadata;
    private $expand;

    public function formData($invoice)
    {
        $this->setCalculation($invoice->getStripeTaxCalculationId())
            ->setReference(sprintf('Invoice # %s_%s', $invoice->getIncrementId(), time()))
            ->setMetadata([
                'payment_transaction_id' => $invoice->getOrder()->getPayment()->getLastTransId(),
                'order_id' => $invoice->getOrder()->getIncrementId()
            ])
            ->setExpand(['line_items']);

        return $this;
    }

    public function toArray()
    {
        return [
            self::CALCULATION_FIELD_NAME => $this->getCalculation(),
            self::REFERENCE_FIELD_NAME => $this->getReference(),
            self::METADATA_FIELD_NAME => $this->getMetadata(),
            self::EXPAND_FIELD_NAME => $this->getExpand(),
        ];
    }

    public function getCalculation()
    {
        return $this->calculation;
    }

    public function setCalculation($calculation)
    {
        $this->calculation = $calculation;
        return $this;
    }

    public function getReference()
    {
        return $this->reference;
    }

    public function setReference($reference)
    {
        $this->reference = $reference;
        return $this;
    }

    public function getMetadata()
    {
        return $this->metadata;
    }

    public function setMetadata($metadata)
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getExpand()
    {
        return $this->expand;
    }

    public function setExpand($expand)
    {
        $this->expand = $expand;
        return $this;
    }
}