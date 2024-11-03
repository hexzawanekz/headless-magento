<?php

namespace StripeIntegration\Payments\Gateway\Command\RedirectFlow;

use Magento\Payment\Gateway\CommandInterface;
use Magento\Framework\Exception\LocalizedException;

class OrderCommand implements CommandInterface
{
    private $config;
    private $checkoutSessionFactory;
    private $checkoutSession;

    public function __construct(
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Model\CheckoutSessionFactory $checkoutSessionFactory,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        $this->config = $config;
        $this->checkoutSession = $checkoutSession;
        $this->checkoutSessionFactory = $checkoutSessionFactory;
    }

    public function execute(array $commandSubject): void
    {
        $payment = $commandSubject['payment']->getPayment();
        $amount = $commandSubject['amount'];

        $order = $payment->getOrder();

        // Reset the session
        $this->checkoutSession->setStripePaymentsCheckoutSessionId(null);

        // We don't want to send an order email until the payment is collected asynchronously
        $order->setCanSendNewEmailFlag(false);

        try
        {
            $checkoutSessionModel = $this->checkoutSessionFactory->create()->fromOrder($order, true);
            $checkoutSessionObject = $checkoutSessionModel->getStripeObject();

            $payment->setAdditionalInformation("checkout_session_id", $checkoutSessionObject->id);
            $payment->setAdditionalInformation("payment_action", $this->config->getPaymentAction());
            $payment->setAdditionalInformation("is_transaction_pending", true);
            $this->checkoutSession->setStripePaymentsCheckoutSessionId($checkoutSessionObject->id);
            $this->checkoutSession->setStripePaymentsCheckoutSessionURL($checkoutSessionObject->url);

            $order->getPayment()
                ->setIsTransactionClosed(0)
                ->setIsTransactionPending(true);
        }
        catch (\Stripe\Exception\CardException $e)
        {
            throw new LocalizedException(__($e->getMessage()));
        }
        catch (\Exception $e)
        {
            if (strstr($e->getMessage(), 'Invalid country') !== false) {
                throw new LocalizedException(__('Sorry, this payment method is not available in your country.'));
            }
            throw new LocalizedException(__($e->getMessage()));
        }

        $payment->setAdditionalInformation("payment_location", "Redirect flow");

    }
}
