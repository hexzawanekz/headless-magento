# How to place an order before a 3D Secure payment is collected

## About

We describe here a customization which changes the default behavior of the Stripe module when 3DS authentication is required. In the default design, when 3DS is required, the payment is collected when the 3DS authentication succeeds and before the order is placed.

Using this customization, the order will first be placed in "Pending Payment" status, and the 3DS modal will open right after. If 3DS succeeds, the customer will be redirected to the success page. Stripe will then asynchronously send the charge.succeeded webhook event back to your website, which will cause the order to switch to "Processing" or "Complete" status.

If the customer fails 3DS authentication, or if they abandon the payment process, the order will be automatically canceled via cron after 2-3 hours. During this time, inventory will remain reserved. If you wish to cancel the order sooner, you can configure that via the [Pending Payment Order Lifetime](https://experienceleague.adobe.com/en/docs/commerce-admin/stores-sales/order-management/orders/order-scheduled-operations#set-pending-payment-order-lifetime) setting in the admin area.

## Create a new module

Create a new module with the following directory structure. Replace `Vendor` and `CustomModule` with your preferred vendor and module names.

```
app/code/Vendor/CustomModule/
├── etc/
│   ├── module.xml
│   └── config.xml
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

Inside `etc/config.xml`, override the following settings from the Stripe module:

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Store:etc/config.xsd">
    <default>
        <stripe_settings>
            <manual_authentication>
                <rest_api></rest_api> <!-- Setting rest_api to empty will achieve the desired behavior -->
            </manual_authentication>
        </stripe_settings>
    </default>
</config>
```

Enable the module:

```sh
php bin/magento module:enable Vendor_CustomModule
php bin/magento setup:upgrade
php bin/magento cache:clean
php bin/magento cache:flush
```

## GraphQL considerations

The REST API is used by the majority of Magento themes that are based on the core Luma theme. If you are using a custom storefront which uses GraphQL instead of the REST API, then this behavior is the default, and you do not need to make the change described above.

If however you would like your GraphQL-based storefront to place the order *after* the payment succeeds, you can use the same customization approach with the following config inside `etc/config.xml`:

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Store:etc/config.xsd">
    <default>
        <stripe_settings>
            <manual_authentication>
                <graphql_api>card,link</graphql_api>
            </manual_authentication>
        </stripe_settings>
    </default>
</config>
```