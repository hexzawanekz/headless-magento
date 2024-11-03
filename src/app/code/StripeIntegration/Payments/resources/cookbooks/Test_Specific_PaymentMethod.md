# How to test why a specific payment method does not appear

## About

Certain payment methods will only appear when specific conditions are met. For cases where you are unable to get a specific payment method to appear, it is possible to force-enable it via a small change in the Stripe module. If you then navigate to the checkout page, an error will be displayed which explains the reason that the specific payment method is unavailable.

## Debugging approach

Lets assume that Klarna does not appear at the checkout. To find out the reason, open `Helper/PaymentMethodTypes.php` and make the following adjustment:

```diff
@@ -15,6 +15,8 @@ class PaymentMethodTypes

     public function getPaymentMethodTypes($isExpressCheckout = false)
     {
+        return ['klarna'];
         if ($isExpressCheckout)
         {
             return $this->expressCheckoutConfig->getPaymentMethodTypes();
```

If you now navigate to the checkout page, an error will be displayed explaining why klarna is unavailable.

For more payment method codes, [click here](https://docs.stripe.com/connect/account-capabilities#payment-methods).