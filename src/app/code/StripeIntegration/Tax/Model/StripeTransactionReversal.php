<?php

namespace StripeIntegration\Tax\Model;

use StripeIntegration\Tax\Model\StripeTransactionReversal\Request;
use StripeIntegration\Tax\Helper\Logger;

class StripeTransactionReversal
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

    public function createReversal($creditMemo, $invoice = null)
    {
        try {
            $request = $this->request->formData($creditMemo, $invoice)->toArray();
            $transaction = $this->config->getStripeClient()->tax->transactions->createReversal($request);
            if ($this->isValidResponse($transaction)) {
                $this->taxFlow->creditMemoTransactionSuccessful = true;

                return [
                    'transaction_id' => $transaction->id,
                    'mode' => $this->request->getTransactionStatus(),
                    'line_items_data' => $this->request->getLineItems()->getLineItemsData()
                ];
            }
        } catch (\Exception $e) {
            $errorMessage = 'Issue occurred while reverting tax:' . PHP_EOL . $e->getMessage();
            $this->logger->logError($errorMessage, $e->getTraceAsString());
        }

        return null;
    }

    private function isValidResponse($transaction)
    {
        if ($transaction->id && $transaction->getLastResponse()->code === 200) {
            return true;
        }

        return false;
    }

    public function isEnabled()
    {
        return $this->config->isEnabled();
    }
}