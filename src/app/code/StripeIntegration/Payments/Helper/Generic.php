<?php

namespace StripeIntegration\Payments\Helper;

use Magento\Framework\Validator\Exception;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Exception\LocalizedException;
use StripeIntegration\Payments\Exception\GenericException;
use StripeIntegration\Payments\Exception\PaymentMethodInUse;
use StripeIntegration\Payments\Exception\InvalidPaymentMethod;

class Generic
{
    private $magentoCustomerId = null;
    private $urlBuilder = null;
    private $currentCustomer = null;
    private $productRepository = null;
    private $scopeConfig;
    private $backendSessionQuote;
    private $request;
    private $areaCodeHelper;
    private $storeManager;
    private $invoiceService;
    private $customerSession;
    private $checkoutSession;
    private $coreRegistry;
    private $adminOrderAddressForm;
    private $customerRegistry;
    private $messageManager;
    private $cache;
    private $userContext;
    private $priceCurrency;
    private $customerRepositoryInterface;
    private $creditmemoFactory;
    private $creditmemoService;
    private $paymentExtensionFactory;
    private $customerCollection;
    private $apiFactory;
    private $convert;
    private $storeHelper;
    private $couponFactory;
    private $stripeCouponFactory;
    private $checkoutHelper;
    private $ruleRepository;
    private $invoiceSender;
    private $quoteHelper;
    private $transactionSearchResultFactory;
    private $invoiceRepository;
    private $transactionRepository;
    private $creditmemoRepository;
    private $orderPaymentRepository;
    private $sessionManager;
    private $multishippingQuoteFactory;
    private $productMetadata;
    private $restController;
    private $logger;
    private $configFactory;
    private $subscriptionsHelperFactory;
    private $stripePaymentMethodFactory;
    private $stripeCustomerModelFactory;
    private $orderHelper;
    private $tokenHelper;
    private $checkoutFlow;
    private $config = null;
    private $subscriptionsHelper = null;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Backend\Model\Session\Quote $backendSessionQuote,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Registry $coreRegistry,
        \Magento\Sales\Block\Adminhtml\Order\Create\Form\Address $adminOrderAddressForm,
        \Magento\Customer\Model\CustomerRegistry $customerRegistry,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\App\CacheInterface $cache,
        \Magento\Authorization\Model\UserContextInterface $userContext,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepositoryInterface,
        \Magento\Sales\Model\Order\CreditmemoFactory $creditmemoFactory,
        \Magento\Sales\Model\Service\CreditmemoService $creditmemoService,
        \Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory,
        \StripeIntegration\Payments\Model\ResourceModel\StripeCustomer\Collection $customerCollection,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \StripeIntegration\Payments\Helper\ApiFactory $apiFactory,
        \StripeIntegration\Payments\Helper\Store $storeHelper,
        \StripeIntegration\Payments\Helper\AreaCode $areaCodeHelper,
        \StripeIntegration\Payments\Helper\Order $orderHelper,
        \StripeIntegration\Payments\Helper\Convert $convert,
        \Magento\SalesRule\Model\CouponFactory $couponFactory,
        \StripeIntegration\Payments\Model\CouponFactory $stripeCouponFactory,
        \Magento\Checkout\Helper\Data $checkoutHelper,
        \Magento\SalesRule\Api\RuleRepositoryInterface $ruleRepository,
        \Magento\Sales\Api\Data\TransactionSearchResultInterfaceFactory $transactionSearchResultFactory,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \StripeIntegration\Payments\Helper\Quote $quoteHelper,
        \Magento\Sales\Api\InvoiceRepositoryInterface $invoiceRepository,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Api\CreditmemoRepositoryInterface $creditmemoRepository,
        \Magento\Sales\Api\OrderPaymentRepositoryInterface $orderPaymentRepository,
        \Magento\Framework\Session\SessionManagerInterface $sessionManager,
        \StripeIntegration\Payments\Model\Multishipping\QuoteFactory $multishippingQuoteFactory,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        \StripeIntegration\Payments\Helper\Logger $logger,
        \StripeIntegration\Payments\Helper\Token $tokenHelper,
        \StripeIntegration\Payments\Model\ConfigFactory $configFactory,
        \StripeIntegration\Payments\Helper\SubscriptionsFactory $subscriptionsHelperFactory,
        \StripeIntegration\Payments\Model\Stripe\PaymentMethodFactory $stripePaymentMethodFactory,
        \StripeIntegration\Payments\Plugin\Webapi\Controller\Rest $restController,
        \StripeIntegration\Payments\Model\StripeCustomerFactory $stripeCustomerModelFactory,
        \StripeIntegration\Payments\Model\Checkout\Flow $checkoutFlow
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->backendSessionQuote = $backendSessionQuote;
        $this->request = $request;
        $this->areaCodeHelper = $areaCodeHelper;
        $this->storeManager = $storeManager;
        $this->invoiceService = $invoiceService;
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->coreRegistry = $coreRegistry;
        $this->adminOrderAddressForm = $adminOrderAddressForm;
        $this->customerRegistry = $customerRegistry;
        $this->messageManager = $messageManager;
        $this->urlBuilder = $urlBuilder;
        $this->cache = $cache;
        $this->userContext = $userContext;
        $this->priceCurrency = $priceCurrency;
        $this->customerRepositoryInterface = $customerRepositoryInterface;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoService = $creditmemoService;
        $this->paymentExtensionFactory = $paymentExtensionFactory;
        $this->customerCollection = $customerCollection;
        $this->productRepository = $productRepository;
        $this->apiFactory = $apiFactory;
        $this->storeHelper = $storeHelper;
        $this->convert = $convert;
        $this->orderHelper = $orderHelper;
        $this->couponFactory = $couponFactory;
        $this->stripeCouponFactory = $stripeCouponFactory;
        $this->checkoutHelper = $checkoutHelper;
        $this->ruleRepository = $ruleRepository;
        $this->invoiceSender = $invoiceSender;
        $this->quoteHelper = $quoteHelper;
        $this->transactionSearchResultFactory = $transactionSearchResultFactory;
        $this->invoiceRepository = $invoiceRepository;
        $this->transactionRepository = $transactionRepository;
        $this->creditmemoRepository = $creditmemoRepository;
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->sessionManager = $sessionManager;
        $this->multishippingQuoteFactory = $multishippingQuoteFactory;
        $this->productMetadata = $productMetadata;
        $this->logger = $logger;
        $this->configFactory = $configFactory;
        $this->subscriptionsHelperFactory = $subscriptionsHelperFactory;
        $this->stripePaymentMethodFactory = $stripePaymentMethodFactory;
        $this->restController = $restController;
        $this->stripeCustomerModelFactory = $stripeCustomerModelFactory;
        $this->tokenHelper = $tokenHelper;
        $this->checkoutFlow = $checkoutFlow;
    }

    protected function getBackendSessionQuote()
    {
        return $this->backendSessionQuote->getQuote();
    }

    public function isSecure()
    {
        return $this->request->isSecure();
    }

    public function getSessionQuote()
    {
        return $this->checkoutSession->getQuote();
    }

    public function getCheckoutSession()
    {
        return $this->checkoutSession;
    }

    public function getStoreId()
    {
        return $this->storeHelper->getStoreId();
    }

    public function getCurrentStore()
    {
        return $this->storeManager->getStore();
    }

    public function loadProductBySku($sku)
    {
        try
        {
            return $this->productRepository->get($sku);
        }
        catch (\Exception $e)
        {
            return null;
        }
    }

    public function loadCustomerById($customerId)
    {
        return $this->customerRepositoryInterface->getById($customerId);
    }

    public function isAdmin()
    {
        return $this->areaCodeHelper->isAdmin();
    }

    public function isGraphQLRequest()
    {
        return $this->areaCodeHelper->isGraphQLRequest();
    }

    public function isAPIRequest()
    {
        return $this->areaCodeHelper->isAPIRequest();
    }

    public function isCustomerLoggedIn()
    {
        return $this->customerSession->isLoggedIn();
    }

    public function getCustomerId()
    {
        // If we are in the back office
        if ($this->isAdmin())
        {
            // About to refund/invoice an order
            if ($order = $this->coreRegistry->registry('current_order'))
                return $order->getCustomerId();

            // About to capture an invoice
            if ($invoice = $this->coreRegistry->registry('current_invoice'))
                return $invoice->getCustomerId();

            // Creating a new order from admin
            if ($this->adminOrderAddressForm && $this->adminOrderAddressForm->getCustomerId())
                return $this->adminOrderAddressForm->getCustomerId();
        }
        // If we are on the REST API
        else if ($this->userContext->getUserType() == UserContextInterface::USER_TYPE_CUSTOMER)
        {
            return $this->userContext->getUserId();
        }
        // If we are on the checkout page
        else if ($this->customerSession->isLoggedIn())
        {
            return $this->customerSession->getCustomerId();
        }
        // A webhook has instantiated this object
        else if (!empty($this->magentoCustomerId))
        {
            return $this->magentoCustomerId;
        }

        return null;
    }

    public function getMagentoCustomer()
    {
        if ($this->customerSession->getCustomer()->getEntityId())
            return $this->customerSession->getCustomer();

        $customerId = $this->getCustomerId();
        if (!$customerId)
            return null;

        $customer = $this->customerRegistry->retrieve($customerId);

        if ($customer->getEntityId())
            return $customer;

        return null;
    }

    public function isGuest()
    {
        return !$this->customerSession->isLoggedIn();
    }

    // Should return the email address of guest customers
    public function getCustomerEmail()
    {
        $customer = $this->getMagentoCustomer();

        if (!$customer)
            $customer = $this->getGuestCustomer();

        if (!$customer)
            return null;

        if (!$customer->getEmail())
            return null;

        return trim(strtolower($customer->getEmail()));
    }

    public function getGuestCustomer($order = null)
    {
        if ($order)
        {
            return $this->getAddressFrom($order, 'billing');
        }
        else
            return null;
    }

    public function getMultiCurrencyAmount($payment, $baseAmount)
    {
        $order = $payment->getOrder();
        $grandTotal = $order->getGrandTotal();
        $baseGrandTotal = $order->getBaseGrandTotal();

        $rate = $order->getBaseToOrderRate();
        if ($rate == 0) $rate = 1;

        // Full capture, ignore currency rate in case it changed
        if ($baseAmount == $baseGrandTotal)
            return $grandTotal;
        // Partial capture, consider currency rate but don't capture more than the original amount
        else if (is_numeric($rate))
            return min($baseAmount * $rate, $grandTotal);
        // Not a multicurrency capture
        else
            return $baseAmount;
    }

    public function getAddressFrom($order, $addressType = 'shipping')
    {
        if (!$order) return null;

        $addresses = $order->getAddresses();
        if (!empty($addresses))
        {
            foreach ($addresses as $address)
            {
                if ($address["address_type"] == $addressType)
                    return $address;
            }
        }
        else if ($addressType == "shipping" && $order->getShippingAddress() && $order->getShippingAddress()->getStreet(1))
        {
            return $order->getShippingAddress();
        }
        else if ($addressType == "billing" && $order->getBillingAddress() && $order->getBillingAddress()->getStreet(1))
        {
            return $order->getBillingAddress();
        }

        return null;
    }

    public function isAuthorizationExpired($charge)
    {
        if (!$charge->refunded)
            return false;

        if (empty($charge->refunds->data[0]->reason))
            return false;

        if ($charge->refunds->data[0]->reason == "expired_uncaptured_charge")
            return true;

        return false;
    }

    public function addWarning($msg)
    {
        if (is_string($msg))
            $msg = __($msg);

        $this->messageManager->addWarningMessage($msg);
    }

    public function addError($msg)
    {
        if (is_string($msg))
            $msg = __($msg);

        $this->messageManager->addErrorMessage( $msg );
    }

    public function addSuccess($msg)
    {
        if (is_string($msg))
            $msg = __($msg);

        $this->messageManager->addSuccessMessage( $msg );
    }

    public function logError(string $msg, $trace = null)
    {
        $this->logger->logError($msg, $trace);
    }

    public function logInfo(?string $msg)
    {
        $this->logger->logInfo($msg);
    }

    public function isStripeAPIKeyError($msg)
    {
        $pos1 = stripos($msg, "Invalid API key provided");
        $pos2 = stripos($msg, "No API key provided");
        if ($pos1 !== false || $pos2 !== false)
            return true;

        return false;
    }

    public function cleanError($msg)
    {
        if ($this->isStripeAPIKeyError($msg))
            return "Invalid Stripe API key provided.";

        return $msg;
    }

    public function isMultiShipping($quote = null)
    {
        return $this->quoteHelper->isMultiShipping($quote);
    }

    public function throwError($msg, $e = null)
    {
        $this->logError($msg);

        if ($this->logger->shouldLogExceptionTrace($e))
        {
            if ($e->getMessage() != $msg)
                $this->logError($e->getMessage());

            $this->logError($e->getTraceAsString());
        }

        if ($this->isAdmin())
        {
            throw new CouldNotSaveException(__($msg));
        }
        else if ($this->isAPIRequest())
        {
            if ($this->isGraphQLRequest())
            {
                throw new \Magento\Framework\GraphQl\Exception\GraphQlInputException(__($msg));
            }
            else
            {
                $this->restController->setDisplay(true);
                throw new CouldNotSaveException(__($this->cleanError($msg)), $e);
            }
        }
        else if ($this->isMultiShipping())
        {
            throw new \Magento\Framework\Exception\LocalizedException(__($msg), $e);
        }
        else
        {
            $error = $this->cleanError($msg);
            throw new GenericException($error);
        }
    }

    public function invoiceOrder($order, $transactionId = null, $captureCase = \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE, bool $save = true)
    {
        // This will kick in with "Authorize Only" mode orders, but not with "Authorize & Capture"
        if ($order->canInvoice())
        {
            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->setRequestedCaptureCase($captureCase);

            if ($transactionId)
            {
                $invoice->setTransactionId($transactionId);
                $order->getPayment()->setLastTransId($transactionId);
            }

            $invoice->register();

            if ($save)
            {
                $this->saveInvoice($invoice);
                $this->orderHelper->saveOrder($order);
                $this->sendInvoiceEmail($invoice);
            }

            return $invoice;
        }
        // Invoices have already been generated with either Authorize Only or Authorize & Capture, but have not actually been captured because
        // the source is not chargeable yet. These should have a pending status.
        else
        {
            foreach($order->getInvoiceCollection() as $invoice)
            {
                $canCapture = $invoice->canCapture(); // Used in Authorize Only mode
                $isOpen = $invoice->getState() == \Magento\Sales\Model\Order\Invoice::STATE_OPEN; // Used with admin area Stripe Billing asynchronous payments

                if ($canCapture || $isOpen)
                {
                    $invoice->setRequestedCaptureCase($captureCase);

                    if ($transactionId && !$invoice->getTransactionId())
                    {
                        $invoice->setTransactionId($transactionId);
                        $order->getPayment()->setLastTransId($transactionId);
                    }

                    $invoice->pay();

                    if ($save)
                    {
                        $this->saveInvoice($invoice);
                        $this->orderHelper->saveOrder($order);
                        $this->sendInvoiceEmail($invoice);
                    }

                    return $invoice;
                }
            }
        }

        return null;
    }

    public function cancelOrCloseOrder($order, $refundInvoices = false, $refundOffline = true)
    {
        $canceled = false;

        // When in Authorize & Capture, uncaptured invoices exist, so we should cancel them first
        foreach($order->getInvoiceCollection() as $invoice)
        {
            if ($invoice->getState() == \Magento\Sales\Model\Order\Invoice::STATE_CANCELED)
                continue;

            if ($invoice->canCancel())
            {
                $invoice->cancel();
                $this->saveInvoice($invoice);
                $canceled = true;
            }
            else if ($refundInvoices)
            {
                $creditmemo = $this->creditmemoFactory->createByOrder($order);
                $creditmemo->setInvoice($invoice);
                $this->creditmemoService->refund($creditmemo, $refundOffline);
                $this->saveCreditmemo($creditmemo);
                $canceled = true;
            }
        }

        // When there are no invoices, the order can be canceled
        if ($order->canCancel())
        {
            $order->cancel();
            $canceled = true;
        }

        $this->orderHelper->saveOrder($order);

        return $canceled;
    }

    public function maskError($msg)
    {
        if (stripos($msg, "You must verify a phone number on your Stripe account") === 0)
            return $msg;

        return false;
    }

    public function formatStripePrice($price, string $currency)
    {
        $precision = $this->convert->getCurrencyPrecision($currency);
        $magentoAmount = $this->convert->stripeAmountToMagentoAmount($price, $currency);

        return $this->priceCurrency->format($magentoAmount, false, $precision, null, strtoupper($currency));
    }

    public function getUrl($path, $additionalParams = [])
    {
        $params = ['_secure' => $this->request->isSecure()];
        return $this->urlBuilder->getUrl($path, $params + $additionalParams);
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function sendInvoiceEmail($invoice)
    {
        try
        {
            $this->invoiceSender->send($invoice);
            return true;
        }
        catch (\Exception $e)
        {
            $this->logError($e->getMessage(), $e->getTraceAsString());
        }

        return false;
    }

    // An assumption is made that Webhooks->initStripeFrom($order) has already been called
    // to set the store and currency before the conversion, as the pricingHelper uses those
    public function getFormattedStripeAmount($amount, $currency, $order)
    {
        $orderAmount = $this->convertStripeAmountToOrderAmount($amount, $currency, $order);

        return $this->addCurrencySymbol($orderAmount, $currency);
    }

    public function convertBaseAmountToStoreAmount($baseAmount)
    {
        $store = $this->storeManager->getStore();
        return $store->getBaseCurrency()->convert($baseAmount, $store->getCurrentCurrencyCode());
    }

    public function convertBaseAmountToOrderAmount($baseAmount, $order, $stripeCurrency, $precision = 4)
    {
        $currency = $order->getOrderCurrencyCode();

        if (strtolower($stripeCurrency) == strtolower($order->getOrderCurrencyCode()))
        {
            $rate = $order->getBaseToOrderRate();
            if (empty($rate))
                return $baseAmount; // The base currency and the order currency are the same

            return round(floatval($baseAmount * $rate), $precision);
        }
        else
        {
            $store = $this->storeManager->getStore();
            $amount = $store->getBaseCurrency()->convert($baseAmount, $stripeCurrency);

            return round(floatval($amount), $precision);
        }

        // $rate = $this->currencyFactory->create()->load($order->getBaseCurrencyCode())->getAnyRate($currency);
    }

    public function convertMagentoAmountToStripeAmount($amount, $currency, $allowNegatives = false)
    {
        if (empty($amount) || !is_numeric($amount))
            return 0;

        if (!$allowNegatives && $amount < 0)
            return 0;

        return $this->convert->magentoAmountToStripeAmount($amount, $currency);
    }

    public function convertOrderAmountToBaseAmount($amount, $currency, $order)
    {
        if (strtolower($currency) == strtolower($order->getOrderCurrencyCode()))
            $rate = $order->getBaseToOrderRate();
        else
            throw new GenericException("Currency code $currency was not used to place order #" . $order->getIncrementId());

        // $rate = $this->currencyFactory->create()->load($order->getBaseCurrencyCode())->getAnyRate($currency);
        if (empty($rate))
            return $amount; // The base currency and the order currency are the same

        return round(floatval($amount / $rate), 2);
    }

    public function convertStripeAmountToOrderAmount($amount, $currency, $order)
    {
        if (strtolower($currency) != strtolower($order->getOrderCurrencyCode()))
            throw new GenericException("The order currency does not match the Stripe currency");

        return $this->convert->stripeAmountToMagentoAmount($amount, $currency);
    }

    public function convertStripeAmountToQuoteAmount($amount, $currency, $quote)
    {
        if (strtolower($currency) != strtolower($quote->getQuoteCurrencyCode()))
            throw new GenericException("The quote currency does not match the Stripe currency");

        return $this->convert->stripeAmountToMagentoAmount($amount, $currency);
    }

    public function convertStripeAmountToBaseQuoteAmount($amount, $currency, $quote)
    {
        if (strtolower($currency) != strtolower($quote->getQuoteCurrencyCode()))
            throw new GenericException("The order currency does not match the Stripe currency");

        $precision = $this->convert->getCurrencyPrecision($currency);
        $magentoAmount = $this->convert->stripeAmountToMagentoAmount($amount, $currency);

        $baseAmount = round(floatval($magentoAmount / $quote->getBaseToQuoteRate()), $precision);

        return $baseAmount;
    }

    public function getCurrentCurrencyCode()
    {
        return $this->storeManager->getStore()->getCurrentCurrency()->getCode();
    }

    public function getBaseCurrencyCode()
    {
        return $this->storeManager->getStore()->getBaseCurrencyCode();
    }

    public function addCurrencySymbol($amount, $currencyCode = null)
    {
        if (empty($currencyCode))
            $currencyCode = $this->getCurrentCurrencyCode();

        $precision = $this->convert->getCurrencyPrecision($currencyCode);

        return $this->priceCurrency->format($amount, false, $precision, null, strtoupper($currencyCode));
    }

    public function getClearSourceInfo($data)
    {
        $info = [];
        $remove = ['mandate_url', 'fingerprint', 'client_token', 'data_string'];
        foreach ($data as $key => $value)
        {
            if (!in_array($key, $remove))
                $info[$key] = $value;
        }

        // Remove Klarna pay fields
        $startsWith = ["pay_"];
        foreach ($info as $key => $value)
        {
            foreach ($startsWith as $part)
            {
                if (strpos($key, $part) === 0)
                    unset($info[$key]);
            }
        }

        return $info;
    }

    public function getMultishippingOrdersDescription($quote, $orders)
    {
        $customerName = $quote->getCustomerFirstname() . " " . $quote->getCustomerLastname();

        $orderIncrementIds = [];
        foreach ($orders as $order)
            $orderIncrementIds[] = "#" . $order->getIncrementId();

        $description = __("Multishipping orders %1 by %2", implode(", ", $orderIncrementIds), $customerName);

        return $description;
    }

    public function isStripeCheckoutMethod(?string $method)
    {
        if (empty($method))
            return false;

        return in_array($method, ["stripe_payments_checkout"]);
    }

    public function getLevel3DataFrom($order)
    {
        if (empty($order))
            return null;

        $merchantReference = $order->getIncrementId();

        if (empty($merchantReference))
            return null;

        $currency = $order->getOrderCurrencyCode();

        $data = [
            "merchant_reference" => $merchantReference,
            "line_items" => $this->getLevel3DataLineItemsFrom($order)
        ];

        if (!$order->getIsVirtual())
        {
            $data["shipping_address_zip"] = $order->getShippingAddress()->getPostcode();
            $data["shipping_amount"] = $this->convert->magentoAmountToStripeAmount($order->getShippingInclTax(), $currency);
        }

        $data = array_merge($data, $this->getLevel3AdditionalDataFrom($order));

        return $data;
    }

    public function getLevel3DataLineItemsFrom($order)
    {
        $items = [];

        $quoteItems = $order->getAllVisibleItems();
        foreach ($quoteItems as $item)
        {
            $amount = $item->getPrice();
            $currency = $order->getOrderCurrencyCode();
            $tax = $this->convert->magentoAmountToStripeAmount($item->getTaxAmount(), $currency);
            $discount = $this->convert->magentoAmountToStripeAmount($item->getDiscountAmount(), $currency);

            $items[] = [
                "product_code" => substr($item->getSku(), 0, 12),
                "product_description" => substr($item->getName(), 0, 26),
                "unit_cost" => $this->convert->magentoAmountToStripeAmount($amount, $currency),
                "quantity" => $item->getQtyOrdered(),
                "tax_amount" => $tax,
                "discount_amount" => $discount
            ];
        }

        return $items;
    }

    public function getLevel3AdditionalDataFrom($order)
    {
        // You can overwrite to add the shipping_from_zip or customer_reference parameters here
        return [];
    }

    public function getCustomerModel()
    {
        if ($this->currentCustomer)
            return $this->currentCustomer;

        $this->currentCustomer = $this->stripeCustomerModelFactory->create();
        if ($this->currentCustomer->getStripeId())
            return $this->currentCustomer;

        $pk = $this->getPublishableKey();
        if (empty($pk))
            return $this->currentCustomer;

        $customerId = $this->getCustomerId();
        $model = null;

        if (is_numeric($customerId) && $customerId > 0)
        {
            $model = $this->customerCollection->getByCustomerId($customerId, $pk);
            if ($model && $model->getId() && $model->existsInStripe())
            {
                $model->updateSessionId();
                $this->currentCustomer = $model;
            }
        }
        else
        {
            $stripeCustomerId = $this->sessionManager->getStripeCustomerId();
            $model = null;

            if ($stripeCustomerId)
            {
                $model = $this->customerCollection->getByStripeCustomerIdAndPk($stripeCustomerId, $pk);
            }
            else
            {
                $sessionId = $this->sessionManager->getSessionId();
                $model = $this->customerCollection->getBySessionId($sessionId, $pk);
            }

            if ($model && $model->getId() && $model->existsInStripe())
                $this->currentCustomer = $model;
        }

        if (!$this->currentCustomer)
        {
            $this->currentCustomer = $this->stripeCustomerModelFactory->create();
        }

        return $this->currentCustomer;
    }

    public function getCustomerModelByStripeId($stripeId)
    {
        return $this->customerCollection->getByStripeCustomerId($stripeId);
    }

    public function getPublishableKey()
    {
        $storeId = $this->getStoreId();
        $mode = $this->scopeConfig->getValue("payment/stripe_payments_basic/stripe_mode", \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        $pk = $this->scopeConfig->getValue("payment/stripe_payments_basic/stripe_{$mode}_pk", \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);

        if (empty($pk))
            return null;

        return trim($pk);
    }

    public function getStripeUrl($liveMode, $objectType, $id)
    {
        if ($liveMode)
            return "https://dashboard.stripe.com/$objectType/$id";
        else
            return "https://dashboard.stripe.com/test/$objectType/$id";
    }

    public function overrideInvoiceActionComment(\Magento\Sales\Model\Order\Payment $payment, $msg)
    {
        $extensionAttributes = $payment->getExtensionAttributes();
        if ($extensionAttributes === null)
        {
            $extensionAttributes = $this->paymentExtensionFactory->create();
            $payment->setExtensionAttributes($extensionAttributes);
        }

        if (!method_exists($extensionAttributes, "setNotificationMessage"))
        {
            // Magento 2.3.5 and older did not support the notification_message extension attribute, which will cause an exception
            // https://github.com/magento/magento2/blob/2.3.6/app/code/Magento/Sales/etc/extension_attributes.xml#L17
            return;
        }

        $extensionAttributes->setNotificationMessage($msg);
    }

    public function overrideCancelActionComment(\Magento\Sales\Model\Order\Payment $payment, $msg)
    {
        $payment->setMessage($msg);
        $this->overrideInvoiceActionComment($payment, $msg);
    }

    public function capture($token, $payment, $amount, $useSavedCard = false)
    {
        $token = $this->tokenHelper->cleanToken($token);
        $order = $payment->getOrder();

        if ($token == "cannot_capture_subscriptions")
        {
            $msg = __("Subscription items cannot be captured online. Capturing offline instead.");
            $this->addWarning($msg);
            $this->overrideInvoiceActionComment($payment, $msg);
            return;
        }

        try
        {
            $paymentObject = $ch = null;
            $finalAmount = $amountToCapture = 0;

            if (strpos($token, 'pi_') === 0)
            {
                $pi = \Stripe\PaymentIntent::retrieve($token);

                if (empty($pi->charges->data[0]) || $pi->status == "requires_action")
                    $this->throwError(__("The payment for this order has not been authorized yet."));

                $ch = $pi->charges->data[0];
                $paymentObject = $pi;
                $amountToCapture = "amount_to_capture";
            }
            else if (strpos($token, 'ch_') === 0)
            {
                $ch = \Stripe\Charge::retrieve($token);
                $paymentObject = $ch;
                $amountToCapture = "amount";
            }
            else
            {
                $this->throwError(__("We do not know how to capture payments with a token of this format."));
            }

            $currency = $ch->currency;

            if ($currency == strtolower($order->getOrderCurrencyCode()))
                $finalAmount = $this->getMultiCurrencyAmount($payment, $amount);
            else if ($currency == strtolower($order->getBaseCurrencyCode()))
                $finalAmount = $amount;
            else
                $this->throwError(__("Cannot capture payment because it was created using a different currency (%1).", $ch->currency));

            $stripeAmount = $this->convert->magentoAmountToStripeAmount($finalAmount, $currency);

            if ($this->isAuthorizationExpired($ch))
            {
                if ($useSavedCard)
                {
                    return $this->apiFactory->create()->reCreateCharge($payment, $amount, $ch);
                }
                else
                    return $this->throwError("The payment authorization with the customer's bank has expired. If you wish to create a new payment using a saved card, please enable Expired Authorizations from Configuration &rarr; Sales &rarr; Payment Methods &rarr; Stripe &rarr; Card Payments &rarr; Expired Authorizations.");
            }
            else if ($ch->refunded)
            {
                $this->throwError("The amount for this invoice has been refunded in Stripe.");
            }
            else if ($ch->captured)
            {
                $capturedAmount = $ch->amount - $ch->amount_refunded;
                $humanReadableAmount = $this->formatStripePrice($stripeAmount - $capturedAmount, $ch->currency);

                if ($order->getInvoiceCollection()->getSize() > 0)
                {
                    foreach ($order->getInvoiceCollection() as $invoice)
                    {
                        if ($invoice->getState() == \Magento\Sales\Model\Order\Invoice::STATE_PAID)
                        {
                            if ($invoice->getGrandTotal() < $order->getGrandTotal()) // Is this a partial invoice?
                            {
                                if ($useSavedCard)
                                {
                                    $this->apiFactory->create()->reCreateCharge($payment, $amount, $ch);
                                    return;
                                }
                                else
                                {
                                    return $this->throwError("The payment has already been partially captured, and the remaining amount has been released. If you wish to create a new payment using a saved card, please enable Expired Authorizations from Configuration &rarr; Sales &rarr; Payment Methods &rarr; Stripe &rarr; Card Payments &rarr; Expired Authorizations.");
                                }
                            }
                            else
                            {
                                // In theory we should never get in here because Magento cannot Invoice orders which have already been fully invoiced..
                                $msg = __("%1 could not be captured online because it was already captured via Stripe. Capturing %1 offline instead.", $humanReadableAmount);
                                $this->addWarning($msg);
                                $this->orderHelper->addOrderComment($msg, $order);
                                return;
                            }
                        }
                    }
                }

                if ($this->orderHelper->hasTrialSubscriptionsIn($order->getAllItems()))
                {
                    $msg = __("%1 could not be captured online because this cart includes subscriptions which are trialing. Capturing %1 offline instead.", $humanReadableAmount);
                }
                else if (($stripeAmount - $capturedAmount) == 0)
                {
                    // Case with a regular item and a subscription with PaymentElement, before the webhook arrives.
                    $humanReadableAmount = $this->formatStripePrice($stripeAmount, $ch->currency);
                    $msg = __("%1 has already captured via Stripe. The invoice was in Pending status, likely because a webhook could not be delivered to your website. Capturing %1 offline instead.", $humanReadableAmount);
                }
                else
                    $msg = __("%1 could not be captured online because it was already captured via Stripe. Capturing %1 offline instead.", $humanReadableAmount);

                $this->addWarning($msg);
                $this->overrideInvoiceActionComment($payment, $msg);
            }
            else // status == pending
            {
                $availableAmount = $ch->amount;
                if ($availableAmount < $stripeAmount)
                {
                    $available = $this->formatStripePrice($availableAmount, $ch->currency);
                    $requested = $this->formatStripePrice($stripeAmount, $ch->currency);

                    throw new LocalizedException(__("The maximum available amount to capture is %1, but a capture of %2 was requested.", $available, $requested));
                }

                $key = "admin_captured_" . $paymentObject->id;
                try
                {
                    $this->cache->save($value = "1", $key, ["stripe_payments"], $lifetime = 60 * 60);
                    $paymentObject->capture([$amountToCapture => $stripeAmount]);
                }
                catch (\Exception $e)
                {
                    $this->cache->remove($key);
                    throw $e;
                }
            }
        }
        catch (\Exception $e)
        {
            $message = "The provided PaymentMethod cannot be attached";
            if (substr($e->getMessage(), 0, strlen($message)) === $message)
            {
                return $this->throwError("The authorization has expired and the payment method used for this order was not saved. To collect a new payment method, click the Reorder button and select the Stripe Billing payment method.");
            }
            $this->throwError($e->getMessage(), $e);
        }
    }

    public function deduplicatePaymentMethod($customerId, $paymentMethodId, $paymentMethodType, $fingerprint, $stripeClient)
    {
        if ($paymentMethodType != "card" || empty($fingerprint) || empty($customerId) || empty($paymentMethodId))
            return;

        try
        {

            switch ($paymentMethodType)
            {
                case "card":

                    $subscriptions = [];
                    $data = $stripeClient->subscriptions->all(['limit' => 100, 'customer' => $customerId]);
                    foreach ($data->autoPagingIterator() as $subscription)
                        $subscriptions[] = $subscription;

                    $collection = $stripeClient->paymentMethods->all([
                      'customer' => $customerId,
                      'type' => $paymentMethodType
                    ]);

                    foreach ($collection->data as $paymentMethod)
                    {
                        if ($paymentMethod['id'] == $paymentMethodId || $paymentMethod['card']['fingerprint'] != $fingerprint)
                            continue;

                        // Update subscriptions which use the card that will be deleted
                        foreach ($subscriptions as $subscription)
                        {
                            if ($subscription->default_payment_method == $paymentMethod['id'])
                            {
                                try
                                {
                                    $stripeClient->subscriptions->update($subscription->id, ['default_payment_method' => $paymentMethodId]);
                                }
                                catch (\Exception $e)
                                {
                                    $this->logError($e->getMessage(), $e->getTraceAsString());
                                }
                            }
                        }

                        // Detach the card from the customer
                        try
                        {
                            $stripeCustomer = $this->stripeCustomerModelFactory->create()->fromStripeCustomerId($customerId);
                            $stripeCustomer->deletePaymentMethod($paymentMethod['id']);
                        }
                        catch (PaymentMethodInUse|InvalidPaymentMethod $e)
                        {
                            continue;
                        }
                        catch (\Exception $e)
                        {
                            $this->logError($e->getMessage());
                            $this->logError($e->getTraceAsString());
                        }
                    }

                    break;

                default:

                    break;
            }
        }
        catch (\Exception $e)
        {
            $this->logError($e->getMessage());
            $this->logError($e->getTraceAsString());
        }
    }

    public function loadCouponByCouponCode($couponCode)
    {
        return $this->couponFactory->create()->loadByCode($couponCode);
    }

    public function loadRuleByRuleId($ruleId)
    {
        return $this->ruleRepository->getById($ruleId);
    }

    public function loadStripeCouponByRuleId($ruleId)
    {
        return $this->stripeCouponFactory->create()->load($ruleId, 'rule_id');
    }

    public function sendPaymentFailedEmail($quote, $msg)
    {
        try
        {
            if (!$quote)
                $quote = $this->quoteHelper->getQuote();

            $this->checkoutHelper->sendPaymentFailedEmail($quote, $msg);
        }
        catch (\Exception $e)
        {
            $this->logError($e->getMessage(), $e->getTraceAsString());
        }
    }

    public function isRecurringOrder($method)
    {
        try
        {
            $info = $method->getInfoInstance();

            if (!$info)
                return false;

            return $info->getAdditionalInformation("is_recurring_subscription");
        }
        catch (\Exception $e)
        {
            return false;
        }
    }

    public function resetPaymentData($payment)
    {
        if ($payment->getPaymentId())
            return;

        // Reset a previously initialized 3D Secure session
        $payment->setAdditionalInformation('stripejs_token', null)
            ->setAdditionalInformation('token', null)
            ->setAdditionalInformation("is_recurring_subscription", null)
            ->setAdditionalInformation("is_migrated_subscription", null)
            ->setAdditionalInformation("remove_initial_fee", null)
            ->setAdditionalInformation("customer_stripe_id", null)
            ->setAdditionalInformation("payment_location", null)
            ->setAdditionalInformation("cvc_token", null)
            ->setAdditionalInformation("client_secret", null)
            ->setAdditionalInformation("is_subscription_update", null);
    }

    protected function isSavedPaymentMethod(\Stripe\PaymentMethod $paymentMethod)
    {
        if (empty($paymentMethod->customer))
        {
            return false;
        }

        // With subscriptions, the payment method is created with PaymentElement and saved automatically before the order is placed.
        // We don't want to perform CVC checks if this is a freshly created payment method.
        $age = time() - $paymentMethod->created;
        if ($age < (4 * 60))
        {
            return false;
        }

        return true;
    }

    public function assignPaymentData($payment, $data)
    {
        $this->resetPaymentData($payment);

        if ($this->isMultiShipping())
        {
            $payment->setAdditionalInformation("payment_location", "Multishipping checkout");
        }
        else if ($this->isAdmin())
        {
            $payment->setAdditionalInformation("payment_location", "Admin area");
        }
        else if (!empty($data['is_migrated_subscription']))
        {
            $payment->setAdditionalInformation("payment_location", "CLI migrated subscription order");
        }
        else if (!empty($data['is_recurring_subscription']))
        {
            $payment->setAdditionalInformation("payment_location", "Recurring subscription order");
        }
        else if ($this->checkoutFlow->isExpressCheckout)
        {
            if (!empty($data['payment_location']))
            {
                $location = "Express Checkout via " . $data['payment_location'] . " page";
                $payment->setAdditionalInformation("payment_location", $location);
            }
            else
            {
                $payment->setAdditionalInformation("payment_location", "Express Checkout");
            }
        }
        else if (!empty($data['is_subscription_update']))
        {
            $payment->setAdditionalInformation("is_subscription_update", true);
        }

        if (!empty($data['payment_method']))
        {
            $payment->setAdditionalInformation('token', $data['payment_method']);

            if (isset($data['save_payment_method']) && $data['save_payment_method'])
                $payment->setAdditionalInformation('save_payment_method', true);

            $config = $this->getConfig();

            if ($config->reCheckCVCForSavedCards())
            {
                $payment->setAdditionalInformation('cvc_token', null);
                $paymentMethod = $this->stripePaymentMethodFactory->create()->fromPaymentMethodId($data['payment_method'])->getStripeObject();

                if ($paymentMethod->type == "card" && $this->isSavedPaymentMethod($paymentMethod))
                {
                    if (!empty($data['cvc_token']))
                    {
                        $payment->setAdditionalInformation('cvc_token', $data['cvc_token']);
                    }
                    else
                    {
                        throw new LocalizedException(__("Your card has been declined. Please check your CVC and try again."));
                    }
                }
            }

            if ($this->isMultiShipping())
            {
                $payment->setAdditionalInformation('token', $data['payment_method']);

                $quoteId = $payment->getQuoteId();
                $multishippingQuoteModel = $this->multishippingQuoteFactory->create();
                $multishippingQuoteModel->load($quoteId, 'quote_id');
                $multishippingQuoteModel->setQuoteId($quoteId);
                $multishippingQuoteModel->setPaymentMethodId($data['payment_method']);
                $multishippingQuoteModel->save();
            }
        }
        else if (!empty($data['confirmation_token']))
        {
            // Used by the express checkout element
            $payment->setAdditionalInformation('confirmation_token', $data['confirmation_token']);
        }
        else if (!empty($data['is_recurring_subscription']))
            $payment->setAdditionalInformation('is_recurring_subscription', $data['is_recurring_subscription']);

        if (!empty($data['is_migrated_subscription']))
            $payment->setAdditionalInformation('is_migrated_subscription', true);
    }

    /**
     * Transaction interface types
     * const TYPE_PAYMENT = 'payment';
     * const TYPE_ORDER = 'order';
     * const TYPE_AUTH = 'authorization';
     * const TYPE_CAPTURE = 'capture';
     * const TYPE_VOID = 'void';
     * const TYPE_REFUND = 'refund';
     **/
    public function addTransaction($order, $transactionId, $transactionType = "capture", $parentTransactionId = null)
    {
        try
        {
            $payment = $order->getPayment();

            if ($parentTransactionId)
            {
                $payment->setTransactionId($transactionId . "-$transactionType");
                $payment->setParentTransactionId($parentTransactionId);
            }
            else
            {
                $payment->setTransactionId($transactionId);
                $payment->setParentTransactionId(null);
            }

            $transaction = $payment->addTransaction($transactionType, null, true);
            return  $transaction;
        }
        catch (Exception $e)
        {
            $this->logError($e->getMessage(), $e->getTraceAsString());
        }
    }

    public function getOrderTransactions($order)
    {
        $transactions = $this->transactionSearchResultFactory->create()->addOrderIdFilter($order->getId());
        return $transactions->getItems();
    }

    // $orderItemQtys = [$orderItem->getId() => int $qty, ...]
    public function invoiceOrderItems($order, $orderItemQtys, $save = true)
    {
        if (empty($orderItemQtys))
            return null;

        $invoice = $this->invoiceService->prepareInvoice($order, $orderItemQtys);
        $invoice->register();
        $order->setIsInProcess(true);

        if ($save)
        {
            $this->saveInvoice($invoice);
            $this->orderHelper->saveOrder($order);
        }

        return $invoice;
    }

    public function clearCache()
    {
        $this->quoteHelper->clearCache();
        $this->orderHelper->clearCache();
        return $this;
    }

    public function saveInvoice($invoice)
    {
        return $this->invoiceRepository->save($invoice);
    }

    public function saveTransaction($transaction)
    {
        return $this->transactionRepository->save($transaction);
    }

    public function saveCreditmemo($creditmemo)
    {
        return $this->creditmemoRepository->save($creditmemo);
    }

    public function savePayment($payment)
    {
        return $this->orderPaymentRepository->save($payment);
    }

    public function saveProduct($product)
    {
        return $this->productRepository->save($product);
    }

    public function setProcessingState($order, $comment = null, $isCustomerNotified = false)
    {
        $state = \Magento\Sales\Model\Order::STATE_PROCESSING;
        $status = $order->getConfig()->getStateDefaultStatus($state);

        if ($comment)
            $order->setState($state)->addStatusToHistory($status, $comment, $isCustomerNotified);
        else
            $order->setState($state)->setStatus($status);
    }

    public function setOrderState($order, $state, $comment = null, $isCustomerNotified = false)
    {
        $status = $order->getConfig()->getStateDefaultStatus($state);

        if ($comment)
            $order->setState($state)->addStatusToHistory($status, $comment, $isCustomerNotified);
        else
            $order->setState($state)->setStatus($status);
    }

    public function getStripeApiTimeDifference()
    {
        $timeDifference = $this->cache->load("stripe_api_time_difference");
        if (!is_numeric($timeDifference))
        {
            $localTime = time();
            $product = \Stripe\Product::create([
               'name' => 'Time Query',
               'type' => 'service'
            ]);
            $timeDifference = $product->created - ($localTime + 1); // The 1 added second accounts for the delay in creating the product
            $this->cache->save($timeDifference, $key = "stripe_api_time_difference", $tags = ["stripe_payments"], $lifetime = 24 * 60 * 60);
            $product->delete();
        }
        return $timeDifference;
    }

    public function isTransactionId($transactionId)
    {
        if (empty($transactionId))
            return false;

        $isPaymentIntent = (strpos($transactionId, "pi_") === 0);
        $isCharge = (strpos($transactionId, "ch_") === 0);
        return ($isPaymentIntent || $isCharge);
    }

    public function getOrdersByTransactionId($transactionId)
    {
        $orders = [];

        if (!$this->isTransactionId($transactionId))
            return $orders;

        $transactions = $this->transactionSearchResultFactory->create()->addFieldToFilter('txn_id', $transactionId);

        foreach ($transactions as $transaction)
        {
            if (!$transaction->getOrderId())
                continue;

            $orderId = $transaction->getOrderId();
            if (isset($orders[$orderId]))
                continue;

            $order = $this->orderHelper->loadOrderById($orderId);
            if ($order && $order->getId())
                $orders[$orderId] = $order;
        }

        return $orders;
    }

    public function getCache()
    {
        return $this->cache;
    }

    public function removeTransactions($order)
    {
        $this->orderHelper->removeTransactions($order);
    }

    public function magentoVersion($operator, $version)
    {
        $magentoVersion = $this->productMetadata->getVersion();
        return version_compare($magentoVersion, $version, $operator);
    }

    public function getSubscriptionsHelper()
    {
        if (!$this->subscriptionsHelper) {
            $this->subscriptionsHelper = $this->subscriptionsHelperFactory->create();
        }

        return $this->subscriptionsHelper;
    }

    protected function getConfig()
    {
        if (!$this->config) {
            $this->config = $this->configFactory->create();
        }

        return $this->config;
    }
}
