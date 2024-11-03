<?php

namespace StripeIntegration\Payments\Controller\Payment;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;

class Index implements ActionInterface
{
    private $checkoutSession;
    private $orderFactory;
    private $helper;
    private $paymentIntentHelper;
    private $multishippingHelper;
    private $checkoutSessionFactory;
    private $config;
    private $paymentElement;
    private $request;
    private $resultFactory;
    private $messageManager;
    private $quoteHelper;
    private $orderHelper;

    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Helper\Quote $quoteHelper,
        \StripeIntegration\Payments\Helper\Order $orderHelper,
        \StripeIntegration\Payments\Helper\PaymentIntent $paymentIntentHelper,
        \StripeIntegration\Payments\Helper\Multishipping $multishippingHelper,
        \StripeIntegration\Payments\Model\CheckoutSessionFactory $checkoutSessionFactory,
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Model\PaymentElement $paymentElement,
        RequestInterface $request,
        ResultFactory $resultFactory,
        ManagerInterface $messageManager
    )
    {
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;

        $this->helper = $helper;
        $this->quoteHelper = $quoteHelper;
        $this->orderHelper = $orderHelper;
        $this->paymentIntentHelper = $paymentIntentHelper;
        $this->multishippingHelper = $multishippingHelper;
        $this->checkoutSessionFactory = $checkoutSessionFactory;
        $this->config = $config;
        $this->paymentElement = $paymentElement;
        $this->resultFactory = $resultFactory;
        $this->request = $request;
        $this->messageManager = $messageManager;
    }

    public function execute()
    {
        $paymentMethodType = $this->request->getParam('payment_method');

        if ($paymentMethodType == 'stripe_checkout')
            return $this->returnFromStripeCheckout();
        else
            return $this->returnFromPaymentElement();
    }

    private function error($message, $order = null)
    {
        $this->checkoutSession->restoreQuote();

        if ($order)
        {
            $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
            $order->addStatusHistoryComment($message);
            $this->orderHelper->removeTransactions($order);
            $this->helper->cancelOrCloseOrder($order, true, true);
            $this->orderHelper->saveOrder($order);
        }

        $this->messageManager->addErrorMessage($message);
        return $this->redirect('checkout/cart');
    }

    private function returnFromPaymentElement()
    {
        $paymentIntentId = $this->request->getParam('payment_intent');
        $setupIntentId = $this->request->getParam('setup_intent');

        if ($paymentIntentId)
        {
            $paymentIntent = $this->config->getStripeClient()->paymentIntents->retrieve($paymentIntentId, []);
            $setupIntent = null;
        }
        else if ($setupIntentId)
        {
            $paymentIntent = null;
            $setupIntent = $this->config->getStripeClient()->setupIntents->retrieve($setupIntentId, []);
        }
        else
        {
            // The customer was redirected here right from the checkout page, rather than an external URL.
            // This can happen when 3DS was performed on the checkout page, and the redirect is necessary to de-activate the quote.
            return $this->success();
        }

        $quote = $this->checkoutSession->getQuote();

        if ($this->multishippingHelper->isMultishippingQuote(null, $quote))
        {
            if ($this->paymentIntentHelper->isSuccessful($paymentIntent ?? $setupIntent) ||
                $this->paymentIntentHelper->requiresOfflineAction($paymentIntent ?? $setupIntent) ||
                $this->paymentIntentHelper->isAsyncProcessing($paymentIntent ?? $setupIntent))
            {
                $redirectUrl = $this->multishippingHelper->getFinalRedirectUrl($quote->getId());
                return $this->redirect($redirectUrl);
            }
            else
            {
                $message = __('Payment failed. Please try placing the order again.');
                $this->multishippingHelper->setAddressErrorForRemainingOrders($quote, $message);
                $redirectUrl = $this->multishippingHelper->getFinalRedirectUrl($quote->getId());
                $this->multishippingHelper->cancelOrdersForQuoteId($quote->getId(), $message);
                return $this->redirect($redirectUrl);
            }
        }
        else
        {
            if ($paymentIntentId)
                $this->paymentElement->load($paymentIntentId, 'payment_intent_id');
            else
                $this->paymentElement->load($setupIntentId, 'setup_intent_id');

            $orderIncrementId = $this->paymentElement->getOrderIncrementId();

            if (!$orderIncrementId)
            {
                // If this ever hits, there is a bug with saving the order increment ID in the payment element table.
                $orderIncrementId = $this->checkoutSession->getLastRealOrderId();
            }

            // This hits on the multishipping checkout when a redirect-based payment method like PayPal is used.
            if (empty($orderIncrementId))
                return $this->success();

            $order = $this->orderHelper->loadOrderByIncrementId($orderIncrementId);
            if (!$order)
                return $this->error(__("Your order #%1 could not be placed. Please contact us for assistance.", $orderIncrementId));

            if ($this->paymentIntentHelper->isSuccessful($paymentIntent ?? $setupIntent) ||
                $this->paymentIntentHelper->requiresOfflineAction($paymentIntent ?? $setupIntent) ||
                $this->paymentIntentHelper->isAsyncProcessing($paymentIntent ?? $setupIntent))
            {
                return $this->success($order);
            }
            else
            {
                return $this->error(__('Payment failed. Please try placing the order again.'), $order);
            }
        }
    }

    private function returnFromStripeCheckout()
    {
        $sessionId = $this->checkoutSession->getStripePaymentsCheckoutSessionId();
        if (empty($sessionId))
            return $this->error(__("Your order was placed successfully, but your browser session has expired. Please check your email for an order confirmation."));

        $checkoutSessionModel = $this->checkoutSessionFactory->create()->load($sessionId, "checkout_session_id");
        $incrementId = $checkoutSessionModel->getOrderIncrementId();
        if (empty($incrementId))
            return $this->error(__("Cannot resume checkout session. Please contact us for help."));

        $order = $this->orderFactory->create()->loadByIncrementId($incrementId);
        if (!$order->getId())
            return $this->error(__("Your order #%1 could not be placed. Please contact us for assistance.", $incrementId));

        // Retrieve payment intent
        try
        {
            /** @var \Stripe\Checkout\Session $session */
            $session = $this->config->getStripeClient()->checkout->sessions->retrieve($sessionId, ['expand' => ['payment_intent', 'subscription.latest_invoice']]);

            if (empty($session->id))
                return $this->error(__('The checkout session for order #%1 could not be retrieved from Stripe', $incrementId), $order);

            if ($session->status == "complete")
            {
                // Paid subscriptions and normal orders
                return $this->stripeCheckoutSuccess($session, $order);
            }
            else if (!empty($session->payment_intent))
            {
                // Regular orders
                switch ($session->payment_intent->status) {
                    case 'succeeded':
                    case 'processing':
                    case 'requires_capture': // Authorize Only mode
                        return $this->stripeCheckoutSuccess($session, $order);
                    default:
                        break;
                }
            }

            if (!empty($session->payment_intent->last_payment_error->message))
                $error = __('Payment failed: %1. Please try placing the order again.', trim($session->payment_intent->last_payment_error->message, "."));
            else
                $error = __('Payment failed. Please try placing the order again.');

            return $this->error($error, $order);
        }
        catch (\Exception $e)
        {
            $this->helper->logError($e->getMessage(), $e->getTraceAsString());
            return $this->error(__("Your order #%1 could not be placed. Please contact us for assistance.", $incrementId));
        }
    }

    protected function stripeCheckoutSuccess($session, $order)
    {
        if (!empty($session->subscription->latest_invoice->payment_intent))
        {
            $this->config->getStripeClient()->paymentIntents->update($session->subscription->latest_invoice->payment_intent,
              ['description' => $this->orderHelper->getOrderDescription($order)]
            );
        }

        return $this->success($order);
    }

    protected function success($order = null)
    {
        $quote = $this->checkoutSession->getQuote();

        if ($quote && $quote->getId())
        {
            $quote->setIsActive(false);
            $this->quoteHelper->saveQuote($quote);
        }

        if (!$this->checkoutSession->getLastRealOrderId() && $order)
            $this->checkoutSession->setLastRealOrderId($order->getIncrementId());

        $checkoutSession = $this->helper->getCheckoutSession();
        $subscriptionReactivateDetails = $checkoutSession->getSubscriptionReactivateDetails();
        $redirectUrl = '';

        if ($subscriptionReactivateDetails) {
            if (isset($subscriptionReactivateDetails['success_url'])
                && $subscriptionReactivateDetails['success_url']) {
                $redirectUrl = $subscriptionReactivateDetails['success_url'];
            }
            $checkoutSession->setSubscriptionReactivateDetails([]);
        }

        if ($redirectUrl) {
            return $this->redirect($redirectUrl);
        }

        return $this->redirect('checkout/onepage/success');
    }

    public function redirect($url, array $params = [])
    {
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $redirect->setPath($url, $params);

        return $redirect;
    }
}
