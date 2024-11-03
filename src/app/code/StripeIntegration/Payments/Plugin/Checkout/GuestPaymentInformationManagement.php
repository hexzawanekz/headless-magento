<?php

namespace StripeIntegration\Payments\Plugin\Checkout;

class GuestPaymentInformationManagement
{
    private $checkoutSessionModel;

    public function __construct(
        \StripeIntegration\Payments\Model\CheckoutSession $checkoutSessionModel
    ) {
        $this->checkoutSessionModel = $checkoutSessionModel;
    }

    public function afterSavePaymentInformation(
        \Magento\Checkout\Api\GuestPaymentInformationManagementInterface $subject,
        $result,
        $cartId,
        $email,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        \Magento\Quote\Api\Data\AddressInterface $billingAddress = null
    ) {
        $this->checkoutSessionModel->updateCustomerEmail($email);

        return $result;
    }
}
