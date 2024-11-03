<?php

namespace StripeIntegration\Payments\Model;

use StripeIntegration\Payments\Exception\GenericException;
use StripeIntegration\Payments\Exception\PaymentMethodInUse;
use StripeIntegration\Payments\Exception\InvalidPaymentMethod;

class StripeCustomer extends \Magento\Framework\Model\AbstractModel
{
    private $_stripeCustomer = null;
    private $_defaultPaymentMethod = null;

    public $customerCard = null;
    public $paymentMethodsCache = [];

    private $sessionManager;
    private $paymentMethodHelper;
    private $localeHelper;
    private $addressHelper;
    private $config;
    private $helper;
    private $customerSession;
    private $paymentMethodFactory;
    private $resourceModel;
    private $quoteHelper;
    private $orderCollectionFactory;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     */
    public function __construct(
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Helper\Quote $quoteHelper,
        \StripeIntegration\Payments\Helper\Address $addressHelper,
        \StripeIntegration\Payments\Helper\Locale $localeHelper,
        \StripeIntegration\Payments\Helper\PaymentMethod $paymentMethodHelper,
        \StripeIntegration\Payments\Model\Stripe\PaymentMethodFactory $paymentMethodFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Session\SessionManagerInterface $sessionManager,
        \StripeIntegration\Payments\Model\ResourceModel\StripeCustomer $resourceModel,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->config = $config;
        $this->helper = $helper;
        $this->addressHelper = $addressHelper;
        $this->localeHelper = $localeHelper;
        $this->paymentMethodHelper = $paymentMethodHelper;
        $this->paymentMethodFactory = $paymentMethodFactory;
        $this->sessionManager = $sessionManager;
        $this->customerSession = $customerSession;
        $this->resourceModel = $resourceModel;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->quoteHelper = $quoteHelper;

        parent::__construct($context, $registry, $resource, $resourceCollection, $data); // This will also call _construct after DI logic
    }

    // Called by parent::__construct() after DI logic
    protected function _construct()
    {
        $this->_init('StripeIntegration\Payments\Model\ResourceModel\StripeCustomer');
    }

    public function fromStripeCustomerId($stripeCustomerId)
    {
        if (empty($stripeCustomerId))
            return null;

        $this->resourceModel->load($this, $stripeCustomerId, 'stripe_id');

        // For older orders placed by customers that are out of sync
        if (empty($this->getStripeId()))
        {
            $this->setStripeId($stripeCustomerId);
            $this->setLastRetrieved(time());
        }

        $this->_stripeCustomer = null;
        $this->retrieveByStripeID($stripeCustomerId, false);

        return $this;
    }

    public function updateSessionId()
    {
        if (!$this->getStripeId()) return;
        if ($this->helper->isAdmin()) return;

        $sessionId = $this->customerSession->getSessionId();
        if ($sessionId != $this->getSessionId())
        {
            $this->setSessionId($sessionId);
            $this->resourceModel->save($this);
        }
    }

    // Loads the customer from the Stripe API
    public function createStripeCustomerIfNotExists($skipCache = false, $order = null)
    {
        // If the payment method has not yet been selected, skip this step
        // $quote = $this->helper->checkoutSession;
        // $paymentMethod = $quote->getPayment()->getMethod();
        // if (empty($paymentMethod) || $paymentMethod != "stripe_payments") return;

        if (!$this->existsInStripe($skipCache))
        {
            $this->createStripeCustomer($order);
        }

        return $this->retrieveByStripeID();
    }

    public function existsInStripe($skipCache = false)
    {
        if (!$this->getStripeId())
            return false;

        $retrievedSecondsAgo = (time() - $this->getLastRetrieved());

        // if the customer was retrieved from Stripe in the last 10 minutes, we're good to go
        // otherwise retrieve them now to make sure they were not deleted from Stripe somehow
        if (!$skipCache && $retrievedSecondsAgo < (60 * 10))
            return true;

        if (!$this->retrieveByStripeID($this->getStripeId()))
            return false;

        return true;
    }

    public function createStripeCustomer($order = null, $extraParams = null)
    {
        $params = $this->getParams($order);

        if (!empty($extraParams['id']))
            $params['id'] = $extraParams['id'];

        return $this->createNewStripeCustomer($params);
    }

    public function getParams($order = null)
    {
        // Defaults
        $customerFirstname = "";
        $customerLastname = "";
        $customerEmail = "";
        $customerId = 0;

        $customer = $this->helper->getMagentoCustomer();

        if ($customer)
        {
            // Registered Magento customers
            $customerFirstname = $customer->getFirstname();
            $customerLastname = $customer->getLastname();
            $customerEmail = $customer->getEmail();
            $customerId = $customer->getEntityId();
        }
        else if ($order)
        {
            // Guest customers
            $address = $this->helper->getAddressFrom($order, 'billing');
            $customerFirstname = $address->getFirstname();
            $customerLastname = $address->getLastname();
            $customerEmail = $address->getEmail();
            $customerId = 0;
        }
        else
        {
            if ($order && $order->getQuoteId())
                $quote = $this->quoteHelper->getQuote($order->getQuoteId());
            else
                $quote = $this->quoteHelper->getQuote();

            if ($quote)
            {
                // Guest customer at checkout, with Always Save Cards enabled, or with subscriptions in the cart
                $address = $quote->getBillingAddress();
                $customerFirstname = $address->getFirstname();
                $customerLastname = $address->getLastname();
                $customerEmail = $address->getEmail();
                $customerId = 0;
            }
        }

        $params = [
            'magento_customer_id' => $customerId
        ];

        if (empty($customerFirstname) && empty($customerLastname))
            $params["name"] = "Guest";
        else
            $params["name"] = "$customerFirstname $customerLastname";

        if ($customerEmail)
            $params["email"] = $customerEmail;

        if ($this->getStripeId())
            $params["id"] = $this->getStripeId();

        return $params;
    }

    public function createNewStripeCustomer($params)
    {
        try
        {
            if (empty($params))
                return;

            $magentoCustomerId = $params['magento_customer_id'];
            unset($params['magento_customer_id']);

            if (!empty($params["id"]))
            {
                $stripeCustomerId = $params["id"];
                unset($params["id"]);
                try
                {
                    $this->_stripeCustomer = $this->config->getStripeClient()->customers->update($stripeCustomerId, $params);
                }
                catch (\Stripe\Exception\ApiErrorException $e)
                {
                    if ($e->getError()->code == "resource_missing")
                        $this->_stripeCustomer = \Stripe\Customer::create($params);
                }
            }
            else
            {
                $this->_stripeCustomer = \Stripe\Customer::create($params);
            }

            if (!$this->_stripeCustomer)
                return null;

            $this->sessionManager->setStripeCustomerId($this->_stripeCustomer->id);

            $this->setStripeId($this->_stripeCustomer->id);
            $this->setCustomerId($magentoCustomerId);

            $this->setLastRetrieved(time());

            if (!empty($params['email']))
                $this->setCustomerEmail($params['email']);

            $this->setPk($this->config->getPublishableKey());
            $this->updateSessionId();

            $this->resourceModel->save($this);

            return $this->_stripeCustomer;
        }
        catch (\Exception $e)
        {
            if ($this->helper->isStripeAPIKeyError($e->getMessage()))
            {
                $this->config->setIsStripeAPIKeyError(true);
                throw new \StripeIntegration\Payments\Exception\SilentException(__($e->getMessage()));
            }
            $msg = __('Could not set up customer profile: %1', $e->getMessage());
            $this->helper->throwError($msg, $e);
        }
    }

    public function getDefaultPaymentMethod()
    {
        if (isset($this->_defaultPaymentMethod))
            return $this->_defaultPaymentMethod;

        $customer = $this->retrieveByStripeID();

        if (empty($customer->invoice_settings->default_payment_method))
            return null;

        try
        {
            return $this->_defaultPaymentMethod = \Stripe\PaymentMethod::retrieve($customer->invoice_settings->default_payment_method);
        }
        catch (\Exception $e)
        {
            return null;
        }
    }

    public function retrieveByStripeID($id = null, $createIfNotExists = true)
    {
        if (isset($this->_stripeCustomer))
            return $this->_stripeCustomer;

        if (empty($id))
            $id = $this->getStripeId();

        if (empty($id))
            return null;

        try
        {
            $customerObject = $this->config->getStripeClient()->customers->retrieve($id, []);
            $this->setLastRetrieved(time());

            if (!$customerObject || ($customerObject && isset($customerObject->deleted) && $customerObject->deleted))
                return null;

            $this->resourceModel->save($this);
            return $this->_stripeCustomer = $customerObject;
        }
        catch (\Exception $e)
        {
            if (strpos($e->getMessage(), "No such customer") === 0 && $createIfNotExists)
            {
                return $this->createStripeCustomer();
            }
            else
            {
                $this->helper->addError('Could not retrieve customer profile: '.$e->getMessage());
                return null;
            }
        }
    }

    private function verifyCanDeletePaymentMethod($paymentMethodId)
    {
        $customerId = $this->getStripeId();
        $stripePaymentMethodModel = $this->paymentMethodFactory->create()->fromPaymentMethodId($paymentMethodId);
        $paymentMethod = $stripePaymentMethodModel->getStripeObject();
        if (!$paymentMethod)
            return;

        // In "Authorize Only" mode, there will be non-successful payment intents associated with orders which are still being processed
        $oneDay = 60 * 60 * 24;
        $created = $paymentMethod->created - $oneDay;
        $paymentIntents = $this->config->getStripeClient()->paymentIntents->all(['customer' => $customerId, 'created' => ['gte' => $created]]);
        $eligiblePaymentIntentIds = [];

        foreach ($paymentIntents->autoPagingIterator() as $paymentIntent)
        {
            if ($paymentIntent->payment_method == $paymentMethodId && $paymentIntent->status != "succeeded")
            {
                $eligiblePaymentIntentIds[] = $paymentIntent->id;
            }
        }

        $statuses = ['processing', 'pending_payment', 'payment_review', 'pending', 'holded'];
        foreach ($eligiblePaymentIntentIds as $paymentIntentId)
        {
            $orders = $this->helper->getOrdersByTransactionId($paymentIntentId);
            foreach ($orders as $order)
            {
                if (in_array($order->getStatus(), $statuses))
                {
                    $message = __("Sorry, it is not possible to delete this payment method because order #%1 which was placed using it is still being processed.", $order->getIncrementId());
                    throw new PaymentMethodInUse($message);
                }
            }
        }

        // In "Order" mode, there will be orders placed with this payment method that do not have any transactions yet
        $orders = $this->getCustomerOrdersWithoutTransaction($paymentMethodId, $statuses, $created);
        foreach ($orders as $order)
        {
            $message = __("Sorry, it is not possible to delete this payment method because order #%1 which was placed using it is still being processed.", $order->getIncrementId());
            throw new PaymentMethodInUse($message);
        }

        if (!$this->getStripeId() || !$paymentMethod->customer || $paymentMethod->customer != $this->getStripeId())
        {
            throw new InvalidPaymentMethod("This payment method could not be deleted. Please contact us for assistance.");
        }
    }

    // Get orders which have a status in $statuses, which have no last_trans_id and which have payment additional_information that includes $paymentMethodId
    private function getCustomerOrdersWithoutTransaction($paymentMethodId, $statuses, $createdAtTimestamp)
    {
        $collection = $this->orderCollectionFactory->create()
            ->addAttributeToSelect('*')
            ->join(
                ['payment' => 'sales_order_payment'],
                'main_table.entity_id = payment.parent_id',
                ['payment_method' => 'payment.method', 'payment_additional_information' => 'payment.additional_information']
            )
            ->addFieldToFilter('main_table.created_at', ['gteq' => $createdAtTimestamp])
            ->addFieldToFilter('main_table.status', ['in' => $statuses])
            ->addFieldToFilter('payment.additional_information', ['like' => '%' . $paymentMethodId . '%']);

        return $collection;
    }

    public function deletePaymentMethod($token, $fingerprint = null)
    {
        if (!$this->_stripeCustomer)
            $this->_stripeCustomer = $this->retrieveByStripeID($this->getStripeId());

        if (!$this->_stripeCustomer)
            throw new GenericException("Customer with ID " . $this->getStripeId() . " could not be retrieved from Stripe.");

        // Deleting a payment method
        if (strpos($token, "pm_") === 0)
        {
            $this->verifyCanDeletePaymentMethod($token);

            if ($fingerprint)
            {
                $allMethods = $this->getSavedPaymentMethods(null, false);
                $newestMethod = null;
                foreach ($allMethods as $type => $methodList)
                {
                    foreach ($methodList as $method)
                    {
                        $type = $method->type;
                        if ($method->{$type}->fingerprint != $fingerprint)
                            continue;

                        if (!$newestMethod || $method->created > $newestMethod->created)
                        {
                            $newestMethod = $this->config->getStripeClient()->paymentMethods->detach($method->id, []);
                        }
                        else
                        {
                            $this->config->getStripeClient()->paymentMethods->detach($method->id, []);
                        }
                    }
                }

                return $newestMethod;
            }
            else
            {
                return $this->config->getStripeClient()->paymentMethods->detach($token, []);
            }
        }
        else if (strpos($token, "src_") === 0 || strpos($token, "card_") === 0)
        {
            return $this->config->getStripeClient()->customers->deleteSource($this->getStripeId(), $token);
        }

        // If we have received a src_ token from an older version of the module
        throw new GenericException("This payment method could not be deleted.");
    }

    public function getSavedPaymentMethods($types = null, $formatted = false)
    {
        if (!$types)
        {
            $types = $this->paymentMethodHelper->getPaymentMethodsThatCanBeSaved();
        }

        if (!$this->getStripeId())
            return [];

        // If the customer cannot manage saved payment methods at the customer account section,
        // then it makes sense that they should also not be able to see them at the checkout page.
        if (!$this->config->getSavePaymentMethod() && !$this->config->alwaysSaveCards())
            return [];

        $methods = [];

        try
        {
            $result = $this->config->getStripeClient()->customers->allPaymentMethods($this->getStripeId(), ['limit' => 30]);
            if (!empty($result->data))
            {
                foreach ($result->data as $method)
                {
                    $type = $method->type;
                    if (in_array($type, $types))
                        $methods[$type][] = $method;
                }
            }
        }
        catch (\Exception $e)
        {
            $this->helper->logError("Cannot retrieve saved payment methods for customer {$this->getStripeId()}: " . $e->getMessage());
        }

        if ($formatted)
        {
            return $this->paymentMethodHelper->formatPaymentMethods($methods);
        }
        else
        {
            return $methods;
        }
    }

    public function getSubscriptionItems($subscriptionId)
    {
        if (empty($subscriptionId))
            return [];

        $params['subscription'] = $subscriptionId;
        $params['expand'] = ['data.price.product'];

        return $this->config->getStripeClient()->subscriptionItems->all($params);
    }

    public function getSubscriptions($params = null)
    {
        $subscriptions = [];

        if (!$this->getStripeId())
            return $subscriptions;

        $params['customer'] = $this->getStripeId();
        $params['limit'] = 100;
        $params['expand'] = ['data.default_payment_method'];

        $collection = \Stripe\Subscription::all($params);

        foreach ($collection->data as $subscription)
        {
            if (in_array($subscription->status, ['canceled', 'incomplete', 'incomplete_expired']))
                continue;

            $subscriptions[$subscription->id] = $subscription;
        }

        return $subscriptions;
    }

    public function getAllSubscriptions()
    {
        $subscriptions = [];

        if (!$this->getStripeId())
            return $subscriptions;

        $params['customer'] = $this->getStripeId();
        $params['status'] = 'all';
        $params['limit'] = 100;
        $params['expand'] = ['data.default_payment_method', 'data.items.data.price', 'data.plan.product'];

        $collection = \Stripe\Subscription::all($params);

        foreach ($collection->autoPagingIterator() as $subscription)
        {
            $subscriptions[$subscription->id] = $subscription;
        }

        return $subscriptions;
    }

    // Creates a customer if they don't exist
    // Updates a customer if they exist
    public function updateFromOrder($order)
    {
        if (!$this->getStripeId())
            return;

        $customer = $this->retrieveByStripeID();

        $data = $this->addressHelper->getStripeAddressFromMagentoAddress($order->getBillingAddress());
        $data['preferred_locales'] = [ $this->localeHelper->getCustomerPreferredLocale() ];

        if (!$order->getIsVirtual())
        {
            $data['shipping'] = $this->addressHelper->getStripeAddressFromMagentoAddress($order->getShippingAddress());
            if (!empty($data['shipping']['email']))
                unset($data['shipping']['email']);
        }

        $this->updateFromData($data);
    }

    public function updateFromData($data)
    {
        if (!$this->getStripeId())
            return;

        $this->_stripeCustomer = $this->config->getStripeClient()->customers->update($this->getStripeId(), $data);
    }

    public function attachPaymentMethod($paymentMethodId)
    {
        if (empty($paymentMethodId))
        {
            throw new GenericException("Invalid payment method ID");
        }

        $stripeCustomerId = $this->getStripeId();
        if (empty($stripeCustomerId))
        {
            throw new GenericException("Could not load customer object");
        }

        try
        {
            $paymentMethod = $this->paymentMethodFactory->create()->fromPaymentMethodId($paymentMethodId)->getStripeObject();
        }
        catch (\Exception $e)
        {
            return $this->helper->throwError("Could not load payment method: " . $e->getMessage(), $e);
        }

        if (empty($paymentMethod->customer))
        {
            return $this->config->getStripeClient()->paymentMethods->attach($paymentMethodId, ['customer' => $this->getStripeId()]);
        }
        else if ($paymentMethod->customer != $this->getStripeId())
        {
            $this->helper->logError("Payment method $paymentMethodId belongs to {$paymentMethod->customer} but was used with customer " . $this->getStripeId());
            return $this->helper->throwError("Could not load payment method.");
        }

        return $paymentMethod;
    }

    // True if the customer is logged into their Magento account
    public function isLoggedIn()
    {
        return $this->customerSession->isLoggedIn();
    }

    public function fromStripeId($customerStripeId)
    {
        $this->resourceModel->load($this, $customerStripeId, 'stripe_id');

        if (!$this->getId())
        {
            $this->syncWithStripe($customerStripeId);
        }

        $this->sessionManager->setStripeCustomerId($customerStripeId);

        return $this;
    }

    public function syncWithStripe($customerStripeId)
    {
        $this->_stripeCustomer = null;

        $this->setStripeId($customerStripeId);

        $customer = $this->helper->getMagentoCustomer();
        if ($customer)
        {
            $this->setCustomerId($customer->getEntityId());
        }

        $customerEmail = $this->helper->getCustomerEmail();
        if ($customerEmail)
        {
            $this->setCustomerEmail($customerEmail);
        }

        $this->setLastRetrieved(time());
        $this->setPk($this->config->getPublishableKey());
        $this->updateSessionId();
        $this->retrieveByStripeID($customerStripeId);
        $this->resourceModel->save($this);
    }
}
