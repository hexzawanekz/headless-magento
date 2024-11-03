<?php

namespace StripeIntegration\Payments\Api;

use StripeIntegration\Payments\Api\ServiceInterface;
use StripeIntegration\Payments\Exception\SCANeededException;
use StripeIntegration\Payments\Exception\InvalidAddressException;
use StripeIntegration\Payments\Exception\GenericException;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequestException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Filter\LocalizedToNormalized;

class Service implements ServiceInterface
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $urlBuilder;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    private $eventManager;

    /**
     * @var \Magento\Checkout\Helper\Data
     */
    private $checkoutHelper;

    /**
     * @var \Magento\Customer\Model\Session
     */
    private $customerSession;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var \StripeIntegration\Payments\Helper\Generic
     */
    private $paymentsHelper;

    /**
     * @var \StripeIntegration\Payments\Model\Config
     */
    private $config;

    /**
     * @var CartManagementInterface
     */
    private $quoteManagement;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var LocalizedToNormalized
     */
    private $localizedToNormalized;
    private $localeHelper;
    private $addressHelper;
    private $compare;
    private $paymentElement;
    private $paymentIntentHelper;
    private $initParams;
    private $multishippingHelper;
    private $paymentIntent;
    private $subscriptionsHelper;
    private $paymentMethodHelper;
    private $checkoutSessionFactory;
    private $stripePaymentIntentFactory;
    private $eceResponseFactory;
    private $quoteRepository;
    private $stripeCustomer;
    private $quoteHelper;
    private $nameParserFactory;
    private $tokenHelper;
    private $checkoutFlow;
    private $orderHelper;
    private $setupIntentHelper;
    private $paymentMethodFactory;

    /**
     * Service constructor.
     *
     * @param StoreManagerInterface                                        $storeManager
     * @param \Magento\Framework\UrlInterface                              $urlBuilder
     * @param \Magento\Framework\Event\ManagerInterface                    $eventManager
     * @param \Magento\Checkout\Helper\Data                                $checkoutHelper
     * @param \Magento\Customer\Model\Session                              $customerSession
     * @param \Magento\Checkout\Model\Session                              $checkoutSession
     * @param \StripeIntegration\Payments\Helper\Generic                     $paymentsHelper
     * @param \StripeIntegration\Payments\Model\Config                       $config
     * @param \Magento\Quote\Api\CartRepositoryInterface                   $quoteRepository
     * @param CartManagementInterface                                      $quoteManagement
     * @param ProductRepositoryInterface                                   $productRepository
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Checkout\Helper\Data $checkoutHelper,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Serialize\SerializerInterface $serializer,
        \Magento\Framework\Filter\LocalizedToNormalized $localizedToNormalized,
        \StripeIntegration\Payments\Helper\Generic $paymentsHelper,
        \StripeIntegration\Payments\Helper\Order $orderHelper,
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Model\PaymentElement $paymentElement,
        \StripeIntegration\Payments\Model\CheckoutSessionFactory $checkoutSessionFactory,
        \StripeIntegration\Payments\Model\Checkout\Flow $checkoutFlow,
        \StripeIntegration\Payments\Model\Stripe\PaymentIntentFactory $stripePaymentIntentFactory,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        CartManagementInterface $quoteManagement,
        ProductRepositoryInterface $productRepository,
        \StripeIntegration\Payments\Model\PaymentIntent $paymentIntent,
        \StripeIntegration\Payments\Helper\Address $addressHelper,
        \StripeIntegration\Payments\Helper\Locale $localeHelper,
        \StripeIntegration\Payments\Helper\Subscriptions $subscriptionsHelper,
        \StripeIntegration\Payments\Helper\Compare $compare,
        \StripeIntegration\Payments\Helper\Multishipping $multishippingHelper,
        \StripeIntegration\Payments\Helper\InitParams $initParams,
        \StripeIntegration\Payments\Helper\PaymentMethod $paymentMethodHelper,
        \StripeIntegration\Payments\Helper\PaymentIntent $paymentIntentHelper,
        \StripeIntegration\Payments\Helper\Quote $quoteHelper,
        \StripeIntegration\Payments\Helper\Token $tokenHelper,
        \StripeIntegration\Payments\Helper\SetupIntent $setupIntentHelper,
        \StripeIntegration\Payments\Api\Response\ECEResponseFactory $eceResponseFactory,
        \StripeIntegration\Payments\Model\Customer\NameParserFactory $nameParserFactory,
        \StripeIntegration\Payments\Model\Stripe\PaymentMethodFactory $paymentMethodFactory
    ) {
        $this->storeManager = $storeManager;
        $this->urlBuilder = $urlBuilder;
        $this->eventManager = $eventManager;
        $this->checkoutHelper = $checkoutHelper;
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->serializer = $serializer;
        $this->localizedToNormalized = $localizedToNormalized;
        $this->paymentsHelper = $paymentsHelper;
        $this->config = $config;
        $this->paymentElement = $paymentElement;
        $this->checkoutSessionFactory = $checkoutSessionFactory;
        $this->checkoutFlow = $checkoutFlow;
        $this->stripeCustomer = $paymentsHelper->getCustomerModel();
        $this->quoteRepository = $quoteRepository;
        $this->quoteManagement = $quoteManagement;
        $this->productRepository = $productRepository;
        $this->paymentIntent = $paymentIntent;
        $this->addressHelper = $addressHelper;
        $this->localeHelper = $localeHelper;
        $this->subscriptionsHelper = $subscriptionsHelper;
        $this->compare = $compare;
        $this->multishippingHelper = $multishippingHelper;
        $this->initParams = $initParams;
        $this->paymentMethodHelper = $paymentMethodHelper;
        $this->paymentIntentHelper = $paymentIntentHelper;
        $this->stripePaymentIntentFactory = $stripePaymentIntentFactory;
        $this->quoteHelper = $quoteHelper;
        $this->eceResponseFactory = $eceResponseFactory;
        $this->nameParserFactory = $nameParserFactory;
        $this->tokenHelper = $tokenHelper;
        $this->orderHelper = $orderHelper;
        $this->setupIntentHelper = $setupIntentHelper;
        $this->paymentMethodFactory = $paymentMethodFactory;
    }

    /**
     * Returns the Stripe Checkout redirect URL
     * @return string
     */
    public function redirect_url()
    {
        $checkout = $this->checkoutHelper->getCheckout();

        // The order was not placed / not saved because some of some exception
        $lastRealOrderId = $checkout->getLastRealOrderId();
        if (empty($lastRealOrderId))
            throw new LocalizedException(__("Your checkout session has expired. Please refresh the checkout page and try again."));

        // The order was placed, but could not be loaded
        $order = $this->orderHelper->loadOrderByIncrementId($lastRealOrderId);
        if (empty($order) || empty($order->getPayment()))
            throw new LocalizedException(__("Sorry, the order could not be placed. Please contact us for more help."));

        // The order was loaded
        if (empty($checkout->getStripePaymentsCheckoutSessionURL()))
            throw new LocalizedException(__("Sorry, the order could not be placed. Please contact us for more help."));

        $this->restoreQuote();

        $sessionURL = $checkout->getStripePaymentsCheckoutSessionURL();
        return $sessionURL;
    }

    public function ece_shipping_address_changed($newAddress, $location)
    {
        try
        {
            $response = $this->eceResponseFactory->create(['location' => $location])->fromNewShippingAddress($newAddress);
            return $response->serialize();
        }
        catch (\Exception $e)
        {
            throw new CouldNotSaveException(__($e->getMessage()), $e);
        }
    }

    /**
     * Apply Shipping Method
     *
     * @param mixed $address
     * @param string|null $shipping_id
     *
     * @return string
     * @throws CouldNotSaveException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function ece_shipping_rate_changed($address, $shipping_id = null)
    {
        try {
            $response = $this->eceResponseFactory->create()->fromNewShippingRate($address, $shipping_id);
            return $response->serialize();
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__($e->getMessage()), $e);
        }
    }

    public function set_billing_address($data)
    {
        try {
            $quote = $this->quoteHelper->getQuote();

            // Place Order
            $billingAddress = $this->addressHelper->getMagentoAddressFromECEAddress($data);

            // Set Billing Address
            $quote->getBillingAddress()
                  ->addData($billingAddress);

            $quote->setTotalsCollectedFlag(false);

            $this->quoteHelper->saveQuote($quote);
        }
        catch (\Exception $e)
        {
            throw new CouldNotSaveException(__($e->getMessage()), $e);
        }

        return $this->serializer->serialize([
            "results" => null
        ]);
    }

    /**
     * Place Order
     *
     * @param mixed $result
     *
     * @return string
     * @throws CouldNotSaveException
     */
    public function place_order($result, $location)
    {
        $quote = $this->quoteHelper->getQuote();
        $this->checkoutFlow->isExpressCheckout = true;

        try {
            // Create an Order ID for the customer's quote
            $quote->reserveOrderId();

            // Determine the customer name
            if (!empty($result['billingDetails']['name']))
            {
                $payerName = $result['billingDetails']['name'];
            }
            else if (!empty($result['shippingAddress']['name']))
            {
                $payerName = $result['shippingAddress']['name'];
            }
            else
            {
                $payerName = null;
            }

            $payerName = $this->nameParserFactory->create()->fromString($payerName);
            $quote->setCustomerFirstname($payerName->getFirstName());
            $quote->setCustomerMiddlename($payerName->getMiddleName());
            $quote->setCustomerLastname($payerName->getLastName());

            // Set Billing Address
            $billingAddress = $this->addressHelper->getMagentoAddressFromECEAddress($result['billingDetails']);
            $quote->getBillingAddress()
                  ->addData($billingAddress);

            if (!$quote->isVirtual())
            {
                // Set Shipping Address
                try
                {
                    // The shipping address is specified from the product page, minicart or cart page
                    $shippingAddress = $this->addressHelper->getMagentoShippingAddressFromECEResult($result);
                }
                catch (InvalidAddressException $e)
                {
                    // The shipping address is specified at the checkout page
                    $data = $quote->getShippingAddress()->getData();
                    $shippingAddress = $this->addressHelper->filterAddressData($data);
                }

                if ($this->addressHelper->isRegionRequired($shippingAddress["country_id"]))
                {
                    if (empty($shippingAddress["region"]) && empty($shippingAddress["region_id"]))
                    {
                        throw new LocalizedException(__("Please specify a shipping address region/state."));
                    }
                }

                if (empty($shippingAddress["telephone"]) && !empty($billingAddress["telephone"]))
                    $shippingAddress["telephone"] = $billingAddress["telephone"];

                $shipping = $quote->getShippingAddress()
                                  ->addData($shippingAddress);

                // Set Shipping Method
                if (!empty($result['shippingRate']['id']))
                {
                    $shipping->setCollectShippingRates(true)->collectShippingRates()
                        ->setShippingMethod($result['shippingRate']['id']);
                }
                else if (empty($shipping->getShippingMethod()))
                {
                    throw new LocalizedException(__("Could not place order: Please specify a shipping method."));
                }
            }

            // Update totals
            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals();

            // For multi-stripe account configurations, load the correct Stripe API key from the correct store view
            $this->storeManager->setCurrentStore($quote->getStoreId());
            $this->config->initStripe();

            // Set Checkout Method
            if (!$this->customerSession->isLoggedIn())
            {
                // Use Guest Checkout
                $quote->setCheckoutMethod(\Magento\Checkout\Model\Type\Onepage::METHOD_GUEST)
                      ->setCustomerId(null)
                      ->setCustomerEmail($quote->getBillingAddress()->getEmail())
                      ->setCustomerIsGuest(true)
                      ->setCustomerGroupId(\Magento\Customer\Api\Data\GroupInterface::NOT_LOGGED_IN_ID);
            }
            else
            {
                $quote->setCheckoutMethod(\Magento\Checkout\Model\Type\Onepage::METHOD_CUSTOMER);
                $quote->setCustomerId($this->customerSession->getCustomerId());
            }

            $quote->getPayment()->unsPaymentId(); // Causes the Helper/Generic.php::resetPaymentData() method to reset any previous values
            $this->checkoutFlow->isExpressCheckout = true;
            $quote->getPayment()->importData(['method' => 'stripe_payments_express', 'additional_data' => [
                'confirmation_token' => $result['confirmationToken']['id'],
                'payment_location' => $location
            ]]);

            // Place Order
            /** @var \Magento\Sales\Model\Order $order */
            $order = $this->quoteManagement->submit($quote);
            if ($order)
            {
                $this->eventManager->dispatch(
                    'checkout_type_onepage_save_order_after',
                    ['order' => $order, 'quote' => $quote]
                );

                $this->checkoutSession
                    ->setLastQuoteId($quote->getId())
                    ->setLastSuccessQuoteId($quote->getId())
                    ->setLastOrderId($order->getId())
                    ->setLastRealOrderId($order->getIncrementId())
                    ->setLastOrderStatus($order->getStatus());
            }

            $this->eventManager->dispatch(
                'checkout_submit_all_after',
                [
                    'order' => $order,
                    'quote' => $quote
                ]
            );

            return $this->serializer->serialize([
                'redirect' => $this->urlBuilder->getUrl('checkout/onepage/success', ['_secure' => $this->paymentsHelper->isSecure()])
            ]);
        }
        catch (\Exception $e)
        {
            return $this->paymentsHelper->throwError($e->getMessage(), $e);
        }
    }

    private function adjustNestedFields(&$params)
    {
        foreach ($params as $key => $value)
        {
            // Convert keys of the format "super_attribute[123] => 345" to "super_attribute => [ 123 => 345 ]"
            if (preg_match('/^(.*)\[(.*)\]$/', $key, $matches))
            {
                $params[$matches[1]][$matches[2]] = $value;
                unset($params[$key]);
            }
        }
    }

    /**
     * Add to Cart
     *
     * @param mixed $params
     * @param string|null $shipping_id
     *
     * @return string
     * @throws CouldNotSaveException
     */
    public function addtocart($params, $shipping_id = null)
    {
        $this->adjustNestedFields($params);
        $productId = $params['product'];
        $related = $params['related_product'];

        if (isset($params['qty'])) {
            $this->localizedToNormalized->setOptions(['locale' => $this->localeHelper->getLocale()]);
            $params['qty'] = $this->localizedToNormalized->filter((string)$params['qty']);
        }

        $quote = $this->quoteHelper->getQuote();

        try {
            // Get Product
            $storeId = $this->storeManager->getStore()->getId();
            $product = $this->productRepository->getById($productId, false, $storeId);

            $this->eventManager->dispatch(
                'stripe_payments_express_before_add_to_cart',
                ['product' => $product, 'request' => $params]
            );

            $groupedProductIds = [];
            if (!empty($params['super_group']) && is_array($params['super_group']))
            {
                $groupedProductSelections = $params['super_group'];
                $groupedProductIds = array_keys($groupedProductSelections);
            }

            // Check is update required
            foreach ($quote->getAllItems() as $item) {
                if ($item->getProductId() == $productId || in_array($item->getProductId(), $groupedProductIds)) {
                    $item = $this->quoteHelper->removeItem($item->getId());
                }
            }

            // Add Product to Cart
            $item = $this->quoteHelper->addProduct($product->getId(), $params);

            if (!empty($related)) {
                $productIds = explode(',', $related);
                $this->quoteHelper->addProductsByIds($productIds);
            }

            $quote = $this->quoteHelper->saveQuote();

            if ($shipping_id) {
                // Set Shipping Method
                if (!$quote->isVirtual()) {
                    // Set Shipping Method
                    $quote->getShippingAddress()->setShippingMethod($shipping_id)
                             ->setCollectShippingRates(true)
                             ->collectShippingRates();
                }
            }

            // Update totals
            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals();
            $this->quoteHelper->saveQuote($quote);

            return $this->serializer->serialize([]);
        }
        catch (\Exception $e)
        {
            throw new CouldNotSaveException(__($e->getMessage()), $e);
        }
    }

    public function ece_params($location, $productId = null, $attribute = null)
    {
        $response = $this->eceResponseFactory->create()->fromClickAt($location, $productId, $attribute);
        return $response->serialize();
    }

    public function get_trialing_subscriptions($billingAddress = null, $shippingAddress = null, $shippingMethod = null, $couponCode = null)
    {
        $quote = $this->quoteHelper->getQuote();

        if (!empty($billingAddress))
            $quote->getBillingAddress()->addData($this->toSnakeCase($billingAddress));

        if (!empty($shippingAddress))
        {
            $quote->getShippingAddress()->addData($this->toSnakeCase($shippingAddress));

            if (!empty($shippingMethod['carrier_code']) && !empty($shippingMethod['method_code']))
            {
                $code = $shippingMethod['carrier_code'] . "_" . $shippingMethod['method_code'];

                $quote->getShippingAddress()
                        ->setCollectShippingRates(true)
                        ->collectShippingRates()
                        ->setShippingMethod($code);
            }
        }

        if (!empty($couponCode))
            $quote->setCouponCode($couponCode);
        else
            $quote->setCouponCode('');

        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();

        if (!empty($billingAddress))
        {
            // The billing address may be empty at the shopping cart case, in which case the save would fail and reset the data
            // we just set to the old values. On the checkout page, the save is necessary.
            $this->quoteRepository->save($quote);
        }

        $subscriptions = $this->subscriptionsHelper->getTrialingSubscriptionsAmounts($quote);
        return $this->serializer->serialize($subscriptions);
    }

    public function get_checkout_payment_methods($billingAddress, $shippingAddress = null, $shippingMethod = null, $couponCode = null)
    {
        $quote = $this->quoteHelper->getQuote();

        if (!empty($billingAddress))
            $quote->getBillingAddress()->addData($this->toSnakeCase($billingAddress));

        if (!empty($shippingAddress))
            $quote->getShippingAddress()->addData($this->toSnakeCase($shippingAddress));

        if (!empty($couponCode))
            $quote->setCouponCode($couponCode);
        else
            $quote->setCouponCode('');

        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        $this->quoteRepository->save($quote);

        $checkoutSessionModel = $this->checkoutSessionFactory->create()->fromQuote($quote);
        $shouldPlaceNewOrder = !$checkoutSessionModel->getOrder();

        if ($checkoutSessionModel->getCheckoutSessionId())
        {
            $params = $checkoutSessionModel->getParamsFromQuote($quote);

            if ($checkoutSessionModel->isComplete())
            {
                $shouldPlaceNewOrder = true;
            }
            else if ($checkoutSessionModel->hasExpired())
            {
                $shouldPlaceNewOrder = true;
                $checkoutSessionModel->cancelOrder(__("The customer left from the payment page without paying, and the session has expired."));
            }
            else if ($checkoutSessionModel->hasChanged($params))
            {
                $shouldPlaceNewOrder = true;
                $checkoutSessionModel->cancelOrder(__("The customer returned from Stripe and changed the cart details."));
                $checkoutSessionModel->cancelCheckoutSession();
            }
            else if ($checkoutSessionModel->needsUpdate($params))
            {
                $shouldPlaceNewOrder = true;
                $checkoutSessionModel->cancelOrder(__("The customer returned from Stripe and changed their email address."));
                $checkoutSessionModel->cancelCheckoutSession();
            }
        }
        else
        {
            $checkoutSessionModel->createFromQuote($quote);
            $shouldPlaceNewOrder = true;
        }

        $response = [
            "methods" => $checkoutSessionModel->getStripeObject()->payment_method_types,
            "place_order" => $shouldPlaceNewOrder,
            "checkout_session_id" => $checkoutSessionModel->getCheckoutSessionId()
        ];

        return $this->serializer->serialize($response);
    }

    // Get Stripe Checkout session ID, only if it is still valid/open/non-expired AND an order for it exists
    // If an order exists, a redirect is expected. If not, an order placement is expected.
    public function get_checkout_session_id()
    {
        $checkoutSessionModel = $this->checkoutSessionFactory->create();
        $quote = $this->quoteHelper->getQuote();
        /** @var \Stripe\Checkout\Session $session */
        $session = $checkoutSessionModel->fromQuote($quote)->getStripeObject();

        if ($checkoutSessionModel->isComplete() || $checkoutSessionModel->hasExpired())
            return null;

        if (empty($session->id))
            return null;

        if (!$checkoutSessionModel->getOrderIncrementId())
            return null;

        return $session->url;
    }

    /**
     * Restores the quote of the last placed order
     *
     * @api
     *
     * @return mixed
     */
    public function restore_quote()
    {
        try
        {
            $this->restoreQuote();
            return $this->serializer->serialize([]);
        }
        catch (\Exception $e)
        {
            return $this->serializer->serialize([
                "error" => $e->getMessage()
            ]);
        }
    }

    private function restoreQuote()
    {
        $checkout = $this->checkoutHelper->getCheckout();

        /** @var \Magento\Sales\Model\Order $order */
        $order = $checkout->getLastRealOrder();
        if ($order->getId())
        {
            try
            {
                $quote = $this->quoteHelper->loadQuoteById($order->getQuoteId());
                $quote->setIsActive(1)->setReservedOrderId(null);
                $this->quoteHelper->saveQuote($quote);
                $this->checkoutSession->replaceQuote($quote)->setLastRealOrderId($order->getIncrementId());
                return true;
            }
            catch (\Magento\Framework\Exception\NoSuchEntityException $e)
            {
                return false;
            }
        }

        return false;
    }

    /**
     * After a payment failure, and before placing the order for a 2nd time, we call the update_cart method to check if anything
     * changed between the quote and the previously placed order. If it has, we cancel the old order and place a new one.
     *
     * @api
     *
     * @return mixed
     */
    public function update_cart($quoteId = null, $data = null)
    {
        try
        {
            $quote = $this->quoteHelper->getQuote($quoteId);
            if (!$quote || !$quote->getId())
            {
                return $this->serializer->serialize([
                    "placeNewOrder" => true,
                    "reason" => "The quote could not be loaded."
                ]);

                // $this->paymentsHelper->addError(__("Your checkout session has expired. Please try to place the order again."));

                // return $this->serializer->serialize([
                //     "redirect" => $this->paymentsHelper->getUrl("checkout/cart")
                // ]);
            }

            $this->paymentElement->load($quote->getId(), 'quote_id');
            if ($this->paymentElement->getOrderIncrementId())
            {
                $orderIncrementId = $this->paymentElement->getOrderIncrementId();
            }
            else if ($quote->getReservedOrderId())
            {
                $orderIncrementId = $quote->getReservedOrderId();
            }
            else
            {
                return $this->serializer->serialize([
                    "placeNewOrder" => true,
                    "reason" => "The quote does not have an order increment ID."
                ]);
            }

            $order = $this->orderHelper->loadOrderByIncrementId($orderIncrementId);
            if (!$order || !$order->getId())
            {
                return $this->serializer->serialize([
                    "placeNewOrder" => true,
                    "reason" => "Order #$orderIncrementId could not be loaded."
                ]);
            }

            if (in_array($order->getState(), ['canceled', 'complete', 'closed']))
            {
                return $this->serializer->serialize([
                    "placeNewOrder" => true,
                    "reason" => "Order #$orderIncrementId is in an invalid state."
                ]);
            }

            if ($order->getIsMultiShipping())
            {
                return $this->serializer->serialize([
                    "placeNewOrder" => true,
                    "reason" => "This method cannot be used in multi-shipping mode."
                ]);
            }


            if ($this->compare->isDifferent($quote->getData(), [
                "is_virtual" => $order->getIsVirtual(),
                "base_currency_code" => $order->getBaseCurrencyCode(),
                "store_currency_code" => $order->getStoreCurrencyCode(),
                "quote_currency_code" => $order->getOrderCurrencyCode(),
                "global_currency_code" => $order->getGlobalCurrencyCode(),
                "customer_email" => $order->getCustomerEmail(),
                "customer_is_guest" => $order->getCustomerIsGuest(),
                "base_subtotal" => $order->getBaseSubtotal(),
                "subtotal" => $order->getSubtotal(),
                "base_grand_total" => $order->getBaseGrandTotal(),
                "grand_total" => $order->getGrandTotal(),
            ]))
            {
                $msg = __("The order details have changed (%1).", $this->compare->lastReason);
                $this->orderHelper->addOrderComment($msg, $order);
                $this->orderHelper->saveOrder($order);
                return $this->serializer->serialize([
                    "placeNewOrder" => true,
                    "reason" => $msg
                ]);
            }

            $quoteItems = [];
            $orderItems = [];

            foreach ($quote->getAllItems() as $item)
            {
                $quoteItems[$item->getItemId()] = [
                    "sku" => $item->getSku(),
                    "qty" => $item->getQty(),
                    "row_total" => $item->getRowTotal(),
                    "base_row_total" => $item->getBaseRowTotal()
                ];
            }

            foreach ($order->getAllItems() as $item)
            {
                $orderItems[$item->getQuoteItemId()] = [
                    "sku" => $item->getSku(),
                    "qty" => $item->getQtyOrdered(),
                    "row_total" => $item->getRowTotal(),
                    "base_row_total" => $item->getBaseRowTotal()
                ];
            }

            if ($this->compare->isDifferent($quoteItems, $orderItems))
            {
                $msg = __("The order items have changed (%1).", $this->compare->lastReason);
                $this->orderHelper->addOrderComment($msg, $order);
                $this->orderHelper->saveOrder($order);
                return $this->serializer->serialize([
                    "placeNewOrder" => true,
                    "reason" => $msg
                ]);
            }

            if (!$quote->getIsVirtual())
            {
                $expectedData = $this->getAddressComparisonData($order->getShippingAddress()->getData());

                if ($this->compare->isDifferent($quote->getShippingAddress()->getData(), $expectedData))
                {
                    $msg = __("The order shipping address has changed (%1).", $this->compare->lastReason);
                    $this->orderHelper->addOrderComment($msg, $order);
                    $this->orderHelper->saveOrder($order);
                    return $this->serializer->serialize([
                        "placeNewOrder" => true,
                        "reason" => $msg
                    ]);
                }
            }

            if (!$quote->getIsVirtual() && $this->compare->isDifferent($quote->getShippingAddress()->getData(), [
                "shipping_method" => $order->getShippingMethod(),
                "shipping_description" => $order->getShippingDescription(),
                "shipping_amount" => $order->getShippingAmount(),
                "base_shipping_amount" => $order->getBaseShippingAmount()
            ]))
            {
                $msg = __("The order shipping method has changed (%1).", $this->compare->lastReason);
                $this->orderHelper->addOrderComment($msg, $order);
                $this->orderHelper->saveOrder($order);
                return $this->serializer->serialize([
                    "placeNewOrder" => true,
                    "reason" => $msg
                ]);
            }

            $expectedData = $this->getAddressComparisonData($order->getBillingAddress()->getData());

            if ($this->compare->isDifferent($quote->getBillingAddress()->getData(), $expectedData))
            {
                $msg = __("The order billing address has changed (%1).", $this->compare->lastReason);
                $this->orderHelper->addOrderComment($msg, $order);
                $this->orderHelper->saveOrder($order);
                return $this->serializer->serialize([
                    "placeNewOrder" => true,
                    "reason" => $msg
                ]);
            }

            // Invalidate the payment intent.
            try
            {
                if (!empty($data['additional_data']))
                {
                    $payment = $order->getPayment();
                    $this->paymentsHelper->assignPaymentData($payment, $data['additional_data']);
                    $payment->save();
                }

                $this->paymentElement->updateFromOrder($order);

                if ($this->paymentElement->requiresConfirmation() || $this->paymentElement->hasPaymentMethodChanged())
                {
                    $this->paymentElement->confirm($order);
                }
            }
            catch (\Exception $e)
            {
                return $this->serializer->serialize([
                    "error" => $e->getMessage()
                ]);
            }

            return $this->serializer->serialize([
                "placeNewOrder" => false
            ]);
        }
        catch (\Exception $e)
        {
            $this->paymentsHelper->logError($e->getMessage(), $e->getTraceAsString());

            return $this->serializer->serialize([
                "placeNewOrder" => true,
                "reason" => "An error has occurred: " . $e->getMessage()
            ]);
        }
    }

    /**
     * If the last payment requires further action, this returns the client secret of the object that requires action
     *
     * @api
     *
     * @return mixed|null
     */
    public function get_requires_action()
    {
        $orderId = $this->checkoutSession->getLastRealOrderId();
        if (empty($orderId))
        {
            return null;
        }

        $order = $this->orderHelper->loadOrderByIncrementId($orderId);
        if (!$order || !$order->getId())
        {
            return null;
        }

        $intent = $this->getPaymentIntentFromOrder($order); // Works for bank transfers and most APMs after v3.4.x

        if (!$intent)
        {
            $this->paymentElement->fromQuoteId($order->getQuoteId());
            $intent = $this->paymentElement->getPaymentIntent();

            if (!$intent)
                $intent = $this->paymentElement->getSetupIntent();
        }

        if ($intent && $intent->status == "requires_action")
        {
            if (!$this->paymentIntentHelper->requiresOfflineAction($intent))
            {
                // Non-card based confirms may redirect the customer externally. We restore the quote just before it in case the
                // customer clicks the back button on the browser before authenticating the payment.
                $this->restoreQuote();
            }

            return $intent->client_secret;
        }

        return null;
    }

    protected function getPaymentIntentFromOrder($order)
    {
        if (!$order || !$order->getPayment())
        {
            return null;
        }

        $payment = $order->getPayment();

        $transactionId = $this->tokenHelper->cleanToken($payment->getLastTransId());

        if (empty($transactionId) || strpos($transactionId, "pi_") !== 0)
        {
            return null;
        }

        $paymentIntent = $this->stripePaymentIntentFactory->create()->fromPaymentIntentId($transactionId);

        return $paymentIntent->getStripeObject();
    }

    /**
     * Places a multishipping order
     *
     * @api
     * @param int|null $quoteId
     *
     * @return mixed|null $result
     */
    public function place_multishipping_order($quoteId = null)
    {
        if (empty($quoteId))
        {
            $quote = $this->quoteHelper->getQuote();
            $quoteId = $quote->getId();
        }

        try
        {
            $redirectUrl = $this->multishippingHelper->placeOrder($quoteId);
            return $this->serializer->serialize(["redirect" => $redirectUrl]);
        }
        catch (SCANeededException $e)
        {
            return $this->serializer->serialize(["authenticate" => $e->getMessage()]);
        }
        catch (\Exception $e)
        {
            $this->paymentsHelper->logError($e->getMessage(), $e->getTraceAsString());
            return $this->serializer->serialize(["error" => $e->getMessage()]);
        }
    }

    /**
     * Finalizes a multishipping order after a card is declined or customer authentication fails and redirects the customer to the results or success page
     *
     * @api
     * @param string|null $error
     *
     * @return mixed|null $result
     */
    public function finalize_multishipping_order($quoteId = null, $error = null)
    {
        if (empty($quoteId))
        {
            $quote = $this->quoteHelper->getQuote();
            $quoteId = $quote->getId();
        }

        try
        {
            $redirectUrl = $this->multishippingHelper->finalizeOrder($quoteId, $error);
            return $this->serializer->serialize(["redirect" => $redirectUrl]);
        }
        catch (\Exception $e)
        {
            $this->paymentsHelper->logError($e->getMessage(), $e->getTraceAsString());
            return $this->serializer->serialize(["error" => $e->getMessage()]);
        }
    }

    private function getAddressComparisonData($addressData)
    {
        $comparisonFields = ["region_id", "region", "postcode", "lastname", "street", "city", "email", "telephone", "country_id", "firstname", "address_type", "company", "vat_id"];

        $params = [];

        foreach ($comparisonFields as $field)
        {
            if (!empty($addressData[$field]))
                $params[$field] = $addressData[$field];
            else
                $params[$field] = "unset";
        }

        return $params;
    }

    private function toSnakeCase($array)
    {
        $result = [];

        foreach ($array as $key => $value) {
            $key = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $key));
            $result[$key] = $value;
        }

        return $result;
    }

    private function cancelOrder($order, $comment)
    {
        $this->orderHelper->removeTransactions($order);
        $order->addStatusToHistory($status = false, $comment, $isCustomerNotified = false);
        $this->paymentsHelper->cancelOrCloseOrder($order);

        if ($this->paymentIntent->getOrderIncrementId())
        {
            $this->paymentIntent->setOrderIncrementId(null);
            $this->paymentIntent->setOrderId(null);
            $this->paymentIntent->save();
        }
    }

    public function get_upcoming_invoice()
    {
        try
        {
            $data = $this->subscriptionsHelper->getUpcomingInvoice(time());
            return $this->serializer->serialize(["upcomingInvoice" => $data]);
        }
        catch (\Stripe\Exception\InvalidRequestException $e)
        {
            if (strpos($e->getMessage(), "The price specified supports currencies of") !== false)
                return $this->serializer->serialize(["error" => __("Cannot update subscription because the original was purchased in a different currency.")]);

            return $this->serializer->serialize(["error" => $e->getMessage()]);
        }
        catch (\Exception $e)
        {
            $this->paymentsHelper->logError($e->getMessage(), $e->getTraceAsString());
            return $this->serializer->serialize(["error" => $e->getMessage()]);
        }
    }

    /**
     * Add a new saved payment method by ID
     *
     * @api
     * @param string $paymentMethodId
     *
     * @return mixed $paymentMethod
     */
    public function add_payment_method($paymentMethodId)
    {
        if (!$this->stripeCustomer->isLoggedIn())
            throw new LocalizedException(__("The customer is not logged in."));

        if (empty($paymentMethodId))
            throw new LocalizedException(__("Please specify a payment method ID."));

        try
        {
            $paymentMethod = $this->paymentMethodFactory->create()->fromPaymentMethodId($paymentMethodId)->getStripeObject();
            $setupIntentCreateParams = $this->setupIntentHelper->getSavePaymentMethodParams($paymentMethod);
            $setupIntent = $this->config->getStripeClient()->setupIntents->create($setupIntentCreateParams);

            $methods = $this->paymentMethodHelper->formatPaymentMethods([
                $paymentMethod->type => [
                    $paymentMethod
                ]
            ]);

            $method = array_pop($methods);

            if ($this->setupIntentHelper->requiresOnlineAction($setupIntent))
            {
                $method['client_secret'] = $setupIntent->client_secret;
            }

            return $this->serializer->serialize($method);
        }
        catch (StripeInvalidRequestException|GenericException $e)
        {
            throw new CouldNotSaveException(__($e->getMessage()), $e);
        }
        catch (\Exception $e)
        {
            throw new GenericException((string)__("Could not add payment method: %1.", $e->getMessage()));
        }
    }

    /**
     * Delete a saved payment method by ID
     *
     * @api
     * @param string $paymentMethodId
     * @param string $fingerprint
     *
     * @return mixed $result
     */
    public function delete_payment_method($paymentMethodId, $fingerprint = null)
    {
        if (!$this->stripeCustomer->isLoggedIn())
            throw new LocalizedException(__("The customer is not logged in."));

        if (empty($paymentMethodId))
            throw new LocalizedException(__("Please specify a payment method ID."));

        if (!$this->stripeCustomer->getStripeId())
            throw new LocalizedException(__("The payment method does not exist."));

        try
        {
            $paymentMethod = $this->stripeCustomer->deletePaymentMethod($paymentMethodId, $fingerprint);

            if (!empty($paymentMethod->card->last4))
                return $this->serializer->serialize(__("Card •••• %1 has been deleted.", $paymentMethod->card->last4));

            return $this->serializer->serialize(__("The payment method has been deleted."));
        }
        catch (\Exception $e)
        {
            throw new CouldNotSaveException(__($e->getMessage()), $e);
        }
    }

    /**
     * List a customer's saved payment methods
     *
     * @api
     * @return mixed $result
     */
    public function list_payment_methods()
    {
        if (!$this->stripeCustomer->isLoggedIn())
            throw new LocalizedException(__("The customer is not logged in."));

        return $this->serializer->serialize($this->stripeCustomer->getSavedPaymentMethods(null, true));
    }

    /**
     * Cancels the last order placed by the customer, if it's quote ID matches the currently active quote
     *
     * @api
     * @param string $errorMessage
     *
     * @return mixed $result
     */
    public function cancel_last_order($errorMessage)
    {
        try
        {
            $quote = $this->quoteHelper->getQuote();
            $orderId = $this->checkoutSession->getLastRealOrderId();

            if (empty($orderId))
                throw new LocalizedException(__("The customer does not have an order."));

            $order = $this->orderHelper->loadOrderByIncrementId($orderId);

            if (!$order || !$order->getId())
                throw new LocalizedException(__("The order could not be loaded."));

            if ($order->getQuoteId() != $quote->getId())
                throw new LocalizedException(__("The order does not match the current quote."));

            $this->cancelOrder($order, $errorMessage);
        }
        catch (\Exception $e)
        {

        }

        return $this->serializer->serialize([]);
    }

    /**
     * Get Module Configuration for Stripe initialization
     * @return mixed
     */
    public function getStripeConfiguration()
    {
        return $this->initParams->getAPIModuleConfiguration();
    }
}
