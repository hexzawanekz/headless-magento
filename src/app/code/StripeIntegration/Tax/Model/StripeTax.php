<?php

namespace StripeIntegration\Tax\Model;

use StripeIntegration\Tax\Exceptions\IpNotFoundException;
use StripeIntegration\Tax\Exceptions\LocalIpException;
use StripeIntegration\Tax\Helper\Logger;
use StripeIntegration\Tax\Model\StripeTax\Request;
use StripeIntegration\Tax\Model\StripeTax\Response\Cache;
use StripeIntegration\Tax\Model\StripeTax\Response;
use Stripe\Tax\Calculation;

class StripeTax
{
    public const INVALID_REQUEST_ERROR_TYPE = 'invalid_request_error';
    public const CUSTOMER_TAX_LOCATION_INVALID_CODE = 'customer_tax_location_invalid';
    private $hasValidResponse;
    private $config;
    private $request;
    private $responseCache;
    private $response;
    private $logger;
    private $transactionReversalRequest;
    private $requestCache;
    private $taxFlow;

    public function __construct(
        Config          $config,
        Request         $request,
        Cache           $responseCache,
        Response        $response,
        Logger          $logger,
        Request\Cache   $requestCache,
        TaxFlow        $taxFlow
    ) {
        $this->config = $config;
        $this->request = $request;
        $this->responseCache = $responseCache;
        $this->response = $response;
        $this->logger = $logger;
        $this->requestCache = $requestCache;
        $this->taxFlow = $taxFlow;
    }

    public function hasValidResponse()
    {
        return $this->hasValidResponse;
    }

    public function clearResponse()
    {
        $this->hasValidResponse = false;
        $this->response->setData([]);
        $this->responseCache->clear();
    }

    public function calculate($quote, $shippingAssignment, $total)
    {
        try {
            $request = $this->request->formData($quote, $shippingAssignment, $total)->toArray();
            $this->taxFlow->orderTaxCalculationSuccessful = $this->calculateStripeTax($request);
        } catch (LocalIpException | IpNotFoundException $e) {
            $this->logger->debug($e->getMessage());
            $this->response->setData($this->zeroTaxResponse());
        }
    }

    public function calculateForInvoiceTax($order, $invoice)
    {
        $request = $this->request->formDataForInvoiceTax($order, $invoice)->toArray();
        $this->taxFlow->invoiceTaxCalculationSuccessful = $this->calculateStripeTax($request);
    }

    private function calculateStripeTax($request)
    {
        try {
            $this->clearResponse();
            $stripeCalculation = null;
            if ($this->requestCache->getCachedResponse()) {
                $calculation = $this->requestCache->getCachedResponse();
            } else {
                $stripeCalculation = $this->config->getStripeClient()->tax->calculations->create($request);
                $calculation = $this->requestCache->cacheResponse($stripeCalculation);
            }

            if ($this->isValidResponse($calculation, $stripeCalculation)) {
                $this->response->setData($calculation);
                $this->hasValidResponse = true;

                return true;
            }
        } catch (\Exception $e) {
            $this->handleGeneralException($e);
        }

        return false;
    }

    private function handleGeneralException($e)
    {
        $errorMessage = 'Issue encountered at API Tax calculation step:' . PHP_EOL . $e->getMessage();
        if ($this->isInvalidRequest($e->getError()->type)) {
            if ($this->isCustomerTaxLocationInvalid($e->getError()->code)) {
                $this->logger->debug($errorMessage, $e->getTraceAsString());
            } else {
                $this->logger->logError($errorMessage, $e->getTraceAsString());
            }
        } else {
            // in case we find other types of errors which should be handled differently
            $this->logger->logError($errorMessage, $e->getTraceAsString());
        }
        $this->response->setData($this->zeroTaxResponse());
    }

    private function zeroTaxResponse()
    {
        return [
            'currency' => $this->request->getCurrency(),
            'prices' => $this->getLineItemsPrices(),
            'shipping' => $this->request->getShippingCost()->getAmount()
        ];
    }

    private function getLineItemsPrices()
    {
        $prices = [];
        foreach ($this->request->getLineItems() as $lineItem) {
            $prices[$lineItem['reference']] = $lineItem['amount'];
        }
        return $prices;
    }

    private function isInvalidRequest($type)
    {
        return $type == self::INVALID_REQUEST_ERROR_TYPE;
    }

    private function isCustomerTaxLocationInvalid($type)
    {
        return $type == self::CUSTOMER_TAX_LOCATION_INVALID_CODE;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function isValidResponse($calculation, Calculation $stripeCalculation = null)
    {
        // If the calculation is an array it means that it is returned from the cache, which means that it was
        // already checked for validity when it was saved to the cache.
        if (!$stripeCalculation && is_array($calculation)) {
            return true;
        }

        if ($stripeCalculation &&
            !empty($stripeCalculation->line_items->data) &&
            $stripeCalculation->getLastResponse()->code === 200) {
            return true;
        }

        return false;
    }

    public function isEnabled()
    {
        return $this->config->isEnabled();
    }

    public function getStripeApi()
    {
        return $this->config;
    }
}