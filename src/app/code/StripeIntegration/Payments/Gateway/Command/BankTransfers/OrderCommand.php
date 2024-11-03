<?php

namespace StripeIntegration\Payments\Gateway\Command\BankTransfers;

use Magento\Payment\Gateway\CommandInterface;

class OrderCommand implements CommandInterface
{
    private $config;
    private $helper;
    private $addressHelper;
    private $stripePaymentMethodFactory;
    private $bankTransfersHelper;
    private $customer;
    private $orderHelper;
    private $convert;

    public function __construct(
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Model\Stripe\PaymentMethodFactory $stripePaymentMethodFactory,
        \StripeIntegration\Payments\Helper\Generic $helper,
        \StripeIntegration\Payments\Helper\Order $orderHelper,
        \StripeIntegration\Payments\Helper\BankTransfers $bankTransfersHelper,
        \StripeIntegration\Payments\Helper\Address $addressHelper,
        \StripeIntegration\Payments\Helper\Convert $convert
    ) {
        $this->config = $config;
        $this->stripePaymentMethodFactory = $stripePaymentMethodFactory;
        $this->helper = $helper;
        $this->bankTransfersHelper = $bankTransfersHelper;
        $this->addressHelper = $addressHelper;
        $this->orderHelper = $orderHelper;
        $this->convert = $convert;
        $this->customer = $helper->getCustomerModel();
    }

    public function execute(array $commandSubject): void
    {
        $payment = $commandSubject['payment']->getPayment();
        $amount = $commandSubject['amount'];

        $paymentIntent = $this->createPaymentIntent($payment, $amount);

        $payment->setTransactionId($paymentIntent->id);
        $payment->setLastTransId($paymentIntent->id);
        $payment->setIsTransactionClosed(0);
        $payment->setIsFraudDetected(false);
        $payment->setIsTransactionPending(true);
        $payment->setAdditionalInformation("customer_stripe_id", $paymentIntent->customer);
    }

    private function createPaymentIntent($payment, $amount)
    {
        $stripe = $this->config->getStripeClient();
        $order = $payment->getOrder();
        $currency = $order->getOrderCurrencyCode();
        $amount = $this->helper->convertBaseAmountToOrderAmount($amount, $order, $currency);
        $paymentMethodId = $payment->getAdditionalInformation("token");

        $params = [
            "amount" => $this->convert->magentoAmountToStripeAmount($amount, $currency),
            "currency" => strtolower($currency),
            "payment_method" => $paymentMethodId,
            "description" => $this->orderHelper->getOrderDescription($order),
            "metadata" => $this->config->getMetadata($order),
            "customer" => $this->getStripeCustomerId($paymentMethodId, $order),
            "confirm" => true,
            "payment_method_types" => ["customer_balance"],
            "payment_method_options" => $this->bankTransfersHelper->getPaymentMethodOptions()
        ];

        if (!$order->getIsVirtual())
        {
            $address = $order->getShippingAddress();

            if (!empty($address))
            {
                $params['shipping'] = $this->addressHelper->getStripeShippingAddressFromMagentoAddress($address);
            }
        }

        if ($this->config->isReceiptEmailsEnabled())
        {
            $customerEmail = $order->getCustomerEmail();

            if ($customerEmail)
            {
                $params["receipt_email"] = $customerEmail;
            }
        }

        $paymentIntent = $stripe->paymentIntents->create($params);

        return $paymentIntent;
    }

    protected function getStripeCustomerId($paymentMethodId, $order)
    {
        $stripePaymentMethodModel = $this->stripePaymentMethodFactory->create()->fromPaymentMethodId($paymentMethodId);
        if ($stripePaymentMethodModel->getCustomerId())
        {
            return $stripePaymentMethodModel->getCustomerId();
        }
        else if ($this->customer->getStripeId())
        {
            return $this->customer->getStripeId();
        }
        else
        {
            $this->customer->createStripeCustomer($order);
            return $this->customer->getStripeId();
        }

        return null;
    }
}
