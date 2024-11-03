# How to style the payment form at the checkout

## About

It is possible to change the theme or appearance of Stripe's [PaymentElement](https://docs.stripe.com/payments/payment-element) at your checkout page, using the [Appearance API](https://docs.stripe.com/elements/appearance-api).

The Appearance API can accept in its parameters a [theme](https://docs.stripe.com/elements/appearance-api?platform=web#theme) which is prebuilt by Stripe and used as a foundation, [variables](https://docs.stripe.com/elements/appearance-api?platform=web#variables) which set certain CSS to be used across the theme, and [rules](https://docs.stripe.com/elements/appearance-api?platform=web#rules) which can be used to target individual DOM elements within the payment form's iframe.

In the Stripe module, the API parameters are set inside `Helper/InitParams.php::getElementOptions()`. To change them, you will need to override the return value of this method. We describe below how to build a custom module which achieves this.

## Creare a new module

Create a new module with the following directory structure. Replace `Vendor` and `CustomModule` with your preferred vendor and module names.

```
app/code/Vendor/CustomModule/
├── etc/
│   ├── module.xml
│   └── di.xml
├── Plugin/
│   └── Payments/
│       └── Helper/
│           └── InitParamsPlugin.php
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

Inside `etc/di.xml`, define the plugin:

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">
    <type name="StripeIntegration\Payments\Helper\InitParams">
        <plugin
            name="vendor_custommodule_payments_initparams_plugin"
            type="Vendor\CustomModule\Plugin\Payments\Helper\InitParamsPlugin"
            sortOrder="10"
            disabled="false" />
    </type>
</config>
```

Inside `Plugin/Payments/Helper/InitParamsPlugin.php`, create the plugin class:

```php
<?php
namespace Vendor\CustomModule\Plugin\Payments\Helper;

use StripeIntegration\Payments\Helper\InitParams;
use Magento\Quote\Model\Quote;
use Psr\Log\LoggerInterface;

class InitParamsPlugin
{
    /**
     * After plugin for getElementOptions method.
     *
     * @param InitParams $subject
     * @param array $result
     * @return array
     */
    public function afterGetElementOptions(InitParams $subject, $result)
    {
        $result['appearance']['variables']['colorPrimary'] = '#FF5733'; // Example: Primary color
        $result['appearance']['variables']['spacingUnit'] = '4px';       // Example: Spacing unit
        $result['appearance']['rules'] = [
            [
                'selector' => 'button',
                'style' => [
                    'backgroundColor' => '#FF5733',
                    'fontSize' => '16px'
                ]
            ],
            [
                'selector' => 'input',
                'style' => [
                    'borderColor' => '#CCCCCC'
                ]
            ]
        ];

        return $result;
    }
}
```

Enable the module:

```sh
php bin/magento module:enable Vendor_CustomModule
php bin/magento setup:upgrade
php bin/magento cache:clean
php bin/magento cache:flush
```