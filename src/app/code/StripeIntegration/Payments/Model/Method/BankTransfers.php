<?php

namespace StripeIntegration\Payments\Model\Method;

use Magento\Framework\Event\ManagerInterface;
use Magento\Payment\Gateway\Command\CommandManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Validator\ValidatorPoolInterface;
use Magento\Payment\Model\InfoInterface;
use Psr\Log\LoggerInterface;

class BankTransfers extends \Magento\Payment\Model\Method\Adapter
{
    private $helper;
    private $bankTransfersHelper;
    private $config;
    protected $convert;

    public function __construct(
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Helper\BankTransfers $bankTransfersHelper,
        \StripeIntegration\Payments\Helper\Convert $convert,
        ManagerInterface $eventManager,
        ValueHandlerPoolInterface $valueHandlerPool,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        $code,
        $formBlockType,
        $infoBlockType,
        CommandPoolInterface $commandPool = null,
        ValidatorPoolInterface $validatorPool = null,
        CommandManagerInterface $commandExecutor = null,
        LoggerInterface $logger = null
    ) {
        $this->config = $config;
        $this->helper = $helper;
        $this->bankTransfersHelper = $bankTransfersHelper;
        $this->convert = $convert;

        parent::__construct(
            $eventManager,
            $valueHandlerPool,
            $paymentDataObjectFactory,
            $code,
            $formBlockType,
            $infoBlockType,
            $commandPool,
            $validatorPool,
            $commandExecutor,
            $logger
        );
    }

    public function assignData(\Magento\Framework\DataObject $data)
    {
        $additionalData = $data->getAdditionalData();

        if (empty($additionalData["payment_method"]) || strpos($additionalData["payment_method"], "pm_") === false)
        {
            return $this;
        }

        $paymentMethodId = $additionalData["payment_method"];
        /** @var InfoInterface $info */
        $info = $this->getInfoInstance();
        $info->setAdditionalInformation("token", $paymentMethodId);

        return parent::assignData($data);
    }

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        try
        {
            if (!$quote || !$quote->getId())
                return false;

            if (!$this->config->initStripe())
                return false;

            $paymentMethodOptions = $this->bankTransfersHelper->getPaymentMethodOptions();
            if (!$paymentMethodOptions)
                return false;

            $quoteCurrency = $quote->getQuoteCurrencyCode();
            $quoteCountry = $quote->getBillingAddress()->getCountryId();

            if (!$this->isCountryCurrencySupported($quoteCountry, $quoteCurrency))
                return false;

            $quoteBaseAmount = $quote->getBaseGrandTotal();
            $minimumAmount = $this->config->getConfigData("minimum_amount", "bank_transfers");
            if (is_numeric($minimumAmount) && $quoteBaseAmount < $minimumAmount)
                return false;

            return parent::isAvailable($quote);
        }
        catch (\Exception $e)
        {
            $this->helper->logError($e->getMessage());
            return false;
        }
    }

    protected function isCountryCurrencySupported($countryCode, $currency)
    {
        $accountModel = $this->config->getAccountModel();
        $accountCurrency = $accountModel->getDefaultCurrency();
        if ($accountCurrency != strtolower($currency))
        {
            return false;
        }

        switch ($countryCode)
        {
            case "US":
                return $currency == "USD";
            case "GB":
                return $currency == "GBP";
            case "JP":
                return $currency == "JPY";
            case "MX":
                return $currency == "MXN";
            default:
                return $currency == "EUR";
        }
    }
}
