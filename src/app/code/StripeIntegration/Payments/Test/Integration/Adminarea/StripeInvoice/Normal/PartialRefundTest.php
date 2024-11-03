<?php

namespace StripeIntegration\Payments\Test\Integration\Adminarea\StripeInvoice\Normal;

/**
 * Magento 2.3.7-p3 does not enable these at class level
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class PartialRefundTest extends \PHPUnit\Framework\TestCase
{
    private $objectManager;
    private $tests;
    private $quote;
    private $paymentMethodBlock;

    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests($this);
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();
        $this->paymentMethodBlock = $this->objectManager->get(\StripeIntegration\Payments\Block\Adminhtml\SelectPaymentMethod::class);
    }

    /**
     * @magentoConfigFixture current_store payment/stripe_payments/payment_flow 0
     * @magentoConfigFixture current_store payment/stripe_payments/payment_action authorize_capture
     */
    public function testNormalCart()
    {
        $this->quote->createAdmin()
            ->setCustomer('Guest')
            ->setCart("Normal")
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setPaymentMethod("StripeInvoice");

        $this->paymentMethodBlock->getSavedPaymentMethods(); // Creates the customer object
        $order = $this->quote->placeOrder();

        // Pay the invoice
        $invoiceId = $order->getPayment()->getAdditionalInformation("invoice_id");
        $stripeInvoice = $this->tests->stripe()->invoices->retrieve($invoiceId, []);
        $paymentMethod = $this->tests->stripe()->paymentMethods->attach("pm_card_visa", [
            'customer' => $stripeInvoice->customer
        ]);
        $stripeInvoice = $this->tests->stripe()->invoices->pay($invoiceId, [
            'payment_method' => $paymentMethod->id
        ]);
        $this->assertEquals($order->getGrandTotal() * 100, $stripeInvoice->amount_paid);
        $this->tests->event()->trigger("charge.succeeded", $stripeInvoice->charge);
        $this->tests->event()->trigger("invoice.paid", $invoiceId);
        $this->tests->event()->trigger("invoice.payment_succeeded", $stripeInvoice);

        // Partially refund the order
        $order = $this->tests->refreshOrder($order);
        $invoice = $order->getInvoiceCollection()->getFirstItem();
        $this->tests->refundOnline($invoice, ['virtual-product' => 2], $baseShipping = 0);
        $charge = $this->tests->stripe()->charges->retrieve($stripeInvoice->charge, []);
        $this->assertEquals(2165, $charge->amount_refunded);
        $this->tests->event()->trigger("charge.refunded", $charge);

        // Check the order
        $order = $this->tests->refreshOrder($order);
        $this->assertEquals(21.65, $order->getTotalRefunded());
    }
}
