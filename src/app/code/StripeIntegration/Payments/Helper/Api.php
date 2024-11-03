<?php

namespace StripeIntegration\Payments\Helper;

use Magento\Framework\Exception\LocalizedException;
use \Magento\Payment\Model\InfoInterface;

class Api
{
    private $helper;
    private $config;
    private $paymentIntent;
    private $stripeClient;
    private $cache;
    private $paymentIntentCollectionFactory;
    private $paymentMethodFactory;
    private $paymentIntentHelper;

    public function __construct(
        \Magento\Framework\App\CacheInterface $cache,
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Model\PaymentIntent $paymentIntent,
        \StripeIntegration\Payments\Model\Stripe\PaymentMethodFactory $paymentMethodFactory,
        \StripeIntegration\Payments\Model\Stripe\Client $stripeClient,
        \StripeIntegration\Payments\Model\ResourceModel\PaymentIntent\CollectionFactory $paymentIntentCollectionFactory,
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Helper\PaymentIntent $paymentIntentHelper
    ) {
        $this->helper = $helper;
        $this->config = $config;
        $this->paymentIntent = $paymentIntent;
        $this->stripeClient = $stripeClient;
        $this->cache = $cache;
        $this->paymentIntentCollectionFactory = $paymentIntentCollectionFactory;
        $this->paymentMethodFactory = $paymentMethodFactory;
        $this->paymentIntentHelper = $paymentIntentHelper;
    }

    public function retrieveCharge($token)
    {
        if (empty($token))
            return null;

        if (strpos($token, 'pi_') === 0)
        {
            $pi = \Stripe\PaymentIntent::retrieve($token);

            if (empty($pi->charges->data[0]))
                return null;

            return $pi->charges->data[0];
        }
        else if (strpos($token, 'in_') === 0)
        {
            // Subscriptions save the invoice number instead
            $in = \Stripe\Invoice::retrieve(['id' => $token, 'expand' => ['charge']]);

            return $in->charge;
        }

        return \Stripe\Charge::retrieve($token);
    }

    public function reCreateCharge($payment, $baseAmount, \Stripe\Charge $originalCharge)
    {
        $order = $payment->getOrder();

        if (empty($originalCharge->payment_method) || empty($originalCharge->customer))
            throw new LocalizedException(__("The authorization has expired and the original payment method cannot be reused to re-create the payment."));

        $amount = $this->helper->convertBaseAmountToOrderAmount($baseAmount, $payment->getOrder(), $originalCharge->currency, 2);

        if ($amount > 0)
        {
            $quoteId = $order->getQuoteId();

            // We get here if an existing authorization has expired, in which case
            // we want to discard old Payment Intents and create a new one
            $this->paymentIntentCollectionFactory->create()->deleteForQuoteId($quoteId);

            $paymentMethod = $this->paymentMethodFactory->create()->fromPaymentMethodId($originalCharge->payment_method);

            $params = [
                'capture_method' => \StripeIntegration\Payments\Model\PaymentIntent::CAPTURE_METHOD_AUTOMATIC,
                "customer" => $originalCharge->customer,
                "amount" => $this->helper->convertMagentoAmountToStripeAmount($amount, $originalCharge->currency),
                "currency" => $originalCharge->currency,
                'description' => $originalCharge->description,
                'metadata' => json_decode(json_encode($originalCharge->metadata), true),
                'payment_method_types' => [ $paymentMethod->getStripeObject()->type ]
            ];

            if (!empty($originalCharge->shipping))
            {
                $params['shipping'] = json_decode(json_encode($originalCharge->shipping), true);
            }

            $paymentIntent = $this->config->getStripeClient()->paymentIntents->create($params);

            $confirmParams = [
                "use_stripe_sdk" => true,
                "payment_method" => $originalCharge->payment_method,
            ];

            if (!$this->cache->load("no_moto_gate"))
            {
                $confirmParams["payment_method_options"]["card"]["moto"] = "true";
            }
            else
            {
                $confirmParams["off_session"] = true;
            }

            try
            {
                $paymentIntent = $this->stripeClient->adminConfirmPaymentIntent($paymentIntent->id, $confirmParams);
            }
            catch (\Exception $e)
            {
                return $this->helper->throwError($e->getMessage());
            }

            $this->paymentIntent->processSuccessfulOrder($order, $paymentIntent);
            return $paymentIntent;
        }

        return null;
    }

    public function createNewCharge(InfoInterface $payment, $amount)
    {
        $order = $payment->getOrder();
        $customerId = $payment->getAdditionalInformation("customer_stripe_id");
        $currency = $order->getOrderCurrencyCode();
        $amount = $this->helper->convertBaseAmountToOrderAmount($amount, $order, $currency, 2);

        if ($amount > 0)
        {
            $params = $this->paymentIntent->getParamsFrom($order);
            $params['capture_method'] = \StripeIntegration\Payments\Model\PaymentIntent::CAPTURE_METHOD_AUTOMATIC;
            $params["customer"] = $customerId;
            $params["amount"] = $this->helper->convertMagentoAmountToStripeAmount($amount, $currency);
            $params["currency"] = $currency;
            if (isset($params["payment_method_options"]))
                unset($params["payment_method_options"]);

            $paymentIntent = $this->config->getStripeClient()->paymentIntents->create($params);
            $confirmParams = $this->paymentIntentHelper->getAdminConfirmParams($order, $paymentIntent);

            try
            {
                $paymentIntent = $this->stripeClient->adminConfirmPaymentIntent($paymentIntent->id, $confirmParams);
            }
            catch (\Exception $e)
            {
                return $this->helper->throwError($e->getMessage());
            }

            $this->paymentIntent->processSuccessfulOrder($order, $paymentIntent);
            return $paymentIntent;
        }

        return null;
    }
}
