<?php

namespace StripeIntegration\Payments\Api;

interface PaymentMethodOptionsServiceInterface
{
    public function setQuote($quote) : PaymentMethodOptionsServiceInterface;
    public function setSavePaymentMethod($savePaymentMethod) : PaymentMethodOptionsServiceInterface;
    public function getPaymentMethodOptions() : array;
    public function getPaymentElementTerms() : array;
}