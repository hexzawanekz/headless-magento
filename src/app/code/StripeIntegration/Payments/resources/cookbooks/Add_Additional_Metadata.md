# How to add additional metadata to payments

## About

In your Stripe dashboard, when you click on a payment, you may have noticed that certain metadata are already set on the payment, like the order number in Magento and the module version that was used to collect the payment. We describe here how to extend the Stripe module so that additional metadata are added on each payment.

## Create a new module

Create a new module with the following directory structure. Replace `Vendor` and `CustomModule` with your preferred vendor and module names.

```
app/code/Vendor/CustomModule/
├── etc/
│   ├── module.xml
│   └── di.xml
├── Plugin/
│   └── Payments/
│       └── ConfigPlugin.php
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
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">
    <type name="StripeIntegration\Payments\Model\Config">
        <plugin
            name="vendor_custommodule_payments_config_plugin"
            type="Vendor\CustomModule\Plugin\Payments\ConfigPlugin"
            sortOrder="10"
            disabled="false" />
    </type>
</config>
```

Inside `Plugin/Payments/ConfigPlugin.php`, create the an afterMethod interceptor:

```php
<?php
namespace Vendor\CustomModule\Plugin\Payments;

use StripeIntegration\Payments\Model\Config;

class ConfigPlugin
{
    /**
     * After plugin for getMetadata method.
     *
     * @param Config $subject
     * @param array $result
     * @param Order $order
     * @return array
     */
    public function afterGetMetadata(Config $subject, array $result, $order)
    {
        // Add new metadata
        $result['CustomKey1'] = 'CustomValue1';
        $result['CustomKey2'] = 'CustomValue2';

        // You can add dynamic data based on business logic
        // For example, adding customer group
        $customerGroup = $order->getCustomerGroupId();
        $result['Customer Group'] = $customerGroup;

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