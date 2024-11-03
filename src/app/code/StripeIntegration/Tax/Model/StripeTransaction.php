<?php

namespace StripeIntegration\Tax\Model;

use StripeIntegration\Tax\Model\StripeTransaction\Request;
use StripeIntegration\Tax\Helper\Logger;
use Stripe\Tax\Transaction;

class StripeTransaction
{
    private $config;
    private $request;
    private $logger;
    private $taxFlow;

    public function __construct(
        Config $config,
        Request $request,
        Logger $logger,
        TaxFlow $taxFlow
    )
    {
        $this->config = $config;
        $this->request = $request;
        $this->logger = $logger;
        $this->taxFlow = $taxFlow;
    }

    public function createTransaction($invoice)
    {
        try {
            $request = $this->request->formData($invoice)->toArray();
            $transaction = $this->config->getStripeClient()->tax->transactions->createFromCalculation($request);
            if ($this->isValidResponse($transaction)) {
                $this->taxFlow->invoiceTransactionSuccessful = true;

                return $transaction->id;
            }
        } catch (\Exception $e) {
            $errorMessage = 'Issue occurred while creating transaction:' . PHP_EOL . $e->getMessage();
            $this->logger->logError($errorMessage, $e->getTraceAsString());
        }

        return null;
    }

    private function isValidResponse(Transaction $calculation)
    {
        if (!empty($calculation->line_items->data) && $calculation->getLastResponse()->code === 200) {
            return true;
        }

        return false;
    }

    public function isEnabled()
    {
        return $this->config->isEnabled();
    }
}