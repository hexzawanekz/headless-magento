<?php

namespace StripeIntegration\Payments\Block\Multishipping;

class Overview extends \Magento\Framework\View\Element\Template
{
    private $initParams;
    private $multishippingQuoteFactory;
    private $helper;
    private $quoteHelper;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Helper\Quote $quoteHelper,
        \StripeIntegration\Payments\Helper\InitParams $initParams,
        \StripeIntegration\Payments\Model\Multishipping\QuoteFactory $multishippingQuoteFactory,
        array $data = []
    ) {
        $this->helper = $helper;
        $this->quoteHelper = $quoteHelper;
        $this->initParams = $initParams;
        $this->multishippingQuoteFactory = $multishippingQuoteFactory;

        parent::__construct($context, $data);
    }

    public function willPayWithStripe()
    {
        $quote = $this->quoteHelper->getQuote();
        $paymentMethod = $quote->getPayment()->getMethod();
        return (strpos($paymentMethod, "stripe_") === 0);
    }

    public function getStoreCode()
    {
        $store = $this->helper->getCurrentStore();
        return $store->getCode();
    }

    public function getStripeParams()
    {
        return json_decode(json_encode($this->initParams->getMultishippingParams()));
    }

    public function hasPaymentMethod()
    {
        $quote = $this->quoteHelper->getQuote();
        if (empty($quote))
            return false;

        $model = $this->multishippingQuoteFactory->create();
        $model->load($quote->getId(), 'quote_id');

        if (empty($model->getPaymentMethodId()))
        {
            $this->helper->addWarning(__("Please specify a payment method."));
            return "false";
        }

        return "true";
    }
}
