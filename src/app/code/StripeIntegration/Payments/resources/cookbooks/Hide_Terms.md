# How to hide the terms displayed in the PaymentElement form

## About

Various payment methods inside PaymentElement will display terms relevant to the use of that payment method. We describe here how to disable the terms text under the PaymentElement using a custom module.

Please see the [API documentation](https://docs.stripe.com/js/elements_object/create_payment_element#payment_element_create-options-terms) for more.

## Create a new module

Create a new module with the following directory structure. Replace `Vendor` and `CustomModule` with your preferred vendor and module names.

```
app/code/Vendor/CustomModule/
├── etc/
│   ├── module.xml
│   └── di.xml
├── Plugin/
│   └── Payments/
│       └── Service/
│           └── PaymentMethodOptionsServicePlugin.php
├── registration.php
```

Inside `registration.php`, register your module with Magento.

```php
<?php
\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::MODULE,
    'Vendor_CustomModule',
    __DIR__
);
```

Inside `etc/module.xml`, define the module and set up dependencies to ensure it loads after the Stripe module.

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd">
    <module name="Vendor_CustomModule" setup_version="1.0.0">
        <sequence>
            <module name="StripeIntegration_Payments"/>
        </sequence>
    </module>
</config>
```

Inside `etc/di.xml`, define the following plugin:

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">
    <type name="StripeIntegration\Payments\Service\PaymentMethodOptionsService">
        <plugin
            name="vendor_custommodule_payments_paymentmethodoptionsservice_plugin"
            type="Vendor\CustomModule\Plugin\Payments\Service\PaymentMethodOptionsServicePlugin"
            sortOrder="10"
            disabled="false" />
    </type>
</config>
```

Inside `Plugin/Payments/Service/PaymentMethodOptionsServicePlugin.php`, create the plugin class:

```php
<?php
namespace Vendor\CustomModule\Plugin\Payments\Service;

use StripeIntegration\Payments\Service\PaymentMethodOptionsService;

class PaymentMethodOptionsServicePlugin
{
    /**
     * After plugin for getPaymentElementTerms method.
     *
     * @param PaymentMethodOptionsService $subject
     * @param array $result
     * @return array
     */
    public function afterGetPaymentElementTerms(PaymentMethodOptionsService $subject, $result)
    {
        if (isset($result['paypal']))
        {
            // Can be 'auto', 'always', or 'never'. We recommend against using 'auto' due to the usage of deferred intents in the module.
            $result['paypal'] = 'never';
        }

        return $result;
    }
}
```