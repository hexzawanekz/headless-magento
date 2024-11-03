---
title: Use the Stripe Connector for Adobe Commerce (Magento 2)
subtitle: Learn how to install, upgrade, and uninstall the Stripe Connector for Adobe Commerce (Magento 2).
route: /connectors/adobe-commerce/payments/install
redirects:
  - /magento/install
  - /plugins/magento/install
  - /plugins/magento-2/install
  - /plugins/adobe-commerce/install
  - /connectors/adobe-commerce/install
stripe_products: []
---

{% callout type="caution" %}
We recommend that you test the module before installing it in your production environment. If you experience an installation issue, see [Troubleshooting](/connectors/adobe-commerce/payments/troubleshooting).
{% /callout %}

## Install the module {% #install %}

{% tabs pref="integration" defaultValue="marketplace" %}

{% tab title="From the Marketplace (recommended)" id="marketplace" %}

1. Place an order for the module through the [Adobe Marketplace](https://marketplace.magento.com/stripe-stripe-payments.html).

1. Open a terminal and run the following command in your Adobe Commerce directory:

    ```bash
    composer require stripe/stripe-payments
    ```

At this stage, you might have to submit your username and password. Provide your [Adobe Commerce authentication keys](https://devdocs.magento.com/guides/v2.3/install-gde/prereq/connect-auth.html). You can accept to save your credentials when prompted by Composer. If you've saved your keys and see the error `Invalid Credentials`, update your keys in `~/.composer/auth.json` or delete this file and run the command again.

1. Set up the module by running the following commands:

    ```bash
    php bin/magento setup:upgrade
    php bin/magento cache:flush
    php bin/magento cache:clean
    ```

1. If you run Adobe Commerce in production mode, you must also compile and deploy the module's static files.

```bash
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
```
{% /tab %}

{% tab title="From the raw package" id="package" %}

1. Download the [latest version](https://github.com/stripe/stripe-magento2-releases/raw/master/stripe-magento2-latest.tgz) of the module from Stripe.

1. Extract the module in your Adobe Commerce directory.

    ```bash
    tar -xvf stripe-magento2-latest.tgz
    ```

1. Install the Stripe PHP library.

    ```bash
    composer require stripe/stripe-php:^13.15
    ```

1. Set up the module.

    ```bash
    php bin/magento setup:upgrade
    php bin/magento setup:di:compile
    php bin/magento cache:flush
    ```

1. If you run Adobe Commerce in production mode, you must also compile and deploy the module's static files.

    ```bash
    php bin/magento setup:di:compile
    php bin/magento setup:static-content:deploy
    ```
{% /tab %}

{% /tabs %}

## Upgrade the module {% #upgrade %}

Before you upgrade:

- Back up your files and database.
- Start with your test environment.
- Keep a copy of any customization you made to the module’s original code.
- Check out the [CHANGELOG](https://github.com/stripe/stripe-magento2-releases/blob/master/CHANGELOG.md).

Patch releases (x.x.Y) are backward compatible and require no extra development on your side after you upgrade. Minor and major releases might add new features or change code in a backwards incompatible way. If you customized the module’s code, you’ll need to port these customizations after upgrading and resolve any potential conflict.

{% tabs pref="integration" %}

{% tab title="From the Marketplace" id="marketplace" %}

Run the following commands:

```bash
composer remove stripe/stripe-payments
composer require stripe/stripe-payments
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento cache:clean
```
{% /tab %}

{% tab title="From the raw package" id="package" %}

Run the following commands:

```bash
php bin/magento module:disable --clear-static-content StripeIntegration_Payments
rm -rf app/code/StripeIntegration/Payments
tar -xvf stripe-magento2-latest.tgz
php bin/magento module:enable StripeIntegration_Payments
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento cache:flush
php bin/magento cache:cleanStripeIntegration_Payments
```
{% /tab %}

{% /tabs %}

## Uninstall the module {% #uninstall %}

Before you uninstall:

- Backup your files and database.
- Keep a copy of any customization you made to the module’s original code in case you need to reinstall it later.

{% tabs pref="integration" %}

{% tab title="From the Marketplace" id="marketplace" %}

Run the following commands:

```bash
composer remove stripe/stripe-payments
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento cache:clean
```
{% /tab %}

{% tab title="From the raw package" id="package" %}

Run the following commands:

```bash
php bin/magento module:disable --clear-static-content StripeIntegration_Payments
composer remove stripe/stripe-php
rm -rf app/code/StripeIntegration/Payments
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento cache:flush
php bin/magento cache:clean
```
{% /tab %}

{% /tabs %}

## Lifecycle policy

The latest version of the module supports the following versions of Adobe Commerce:

{% table %}
- Release
- Support
---
- Adobe Commerce 2.0 - 2.3.6
- No longer supported, the last compatible version is `stripe/stripe-payments:2.9.5`.
---
- Adobe Commerce 2.3.7 - 2.4.x
- Currently supported, see below for our own lifecycle policy.
{% /table %}

For `stripe/stripe-payments:3.5.*` and newer, we provide new features, bug fixes, and security patches. Older versions are deprecated. Stripe recommends you upgrade at the earliest opportunity. All releases are available in the Adobe Marketplace and in the [stripe-magento2-releases](https://github.com/stripe/stripe-magento2-releases) GitHub repository.

{% see-also %}
* [Configuring the Stripe Connector for Adobe Commerce](/connectors/adobe-commerce/payments/configuration)
* [Using the Adobe Commerce admin panel](/connectors/adobe-commerce/payments/admin)
* [Troubleshooting](/connectors/adobe-commerce/payments/troubleshooting)
{% /see-also %}
