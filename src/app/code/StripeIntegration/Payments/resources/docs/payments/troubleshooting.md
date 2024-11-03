---
title: Troubleshooting for Adobe Commerce
subtitle: Learn how to troubleshoot the Stripe Connector for Adobe Commerce.
route: /connectors/adobe-commerce/payments/troubleshooting
redirects:
  - /magento/troubleshooting
  - /plugins/magento/troubleshooting
  - /plugins/magento-2/troubleshooting
  - /plugins/adobe-commerce/troubleshooting
  - /connectors/adobe-commerce/troubleshooting
stripe_products: []
---

{% aside title="Switching to developer mode" %}
[Enable the developer mode](https://devdocs.magento.com/guides/v2.0/config-guide/cli/config-cli-subcommands-mode.html) to make it easier to find errors.
{% /aside %}

Use this guide to troubleshoot the Stripe Connector for Adobe Commerce.

## Installation issues {% #installation-issues %}

The most common issue during the installation process is getting the following error when using Composer:

``` {% numbered=false %}
Composer package not found: Could not find a matching version of package stripe/stripe-payments
```

If you encounter this problem, follow these steps:

1. Order the module from the [Adobe Marketplace](https://marketplace.magento.com/stripe-stripe-payments.html).
1. Delete the file under `~/.composer/auth.json` in case you entered the wrong Adobe Commerce API keys.
1. Run the Composer command again. You might have to enter a username and password. Make sure that you enter the Adobe Commerce API keys of the account that you used to place the order. You can [get your authentication keys](https://devdocs.magento.com/guides/v2.4/install-gde/prereq/connect-auth.html) from Adobe Commerce.

## Upgrades and caching issues {% #upgrades-and-caching-issues %}

If you upgrade the module but for some reason don't see the new changes, you can manually clear the Adobe Commerce cache by deleting a set of directories. The official Adobe Commerce documentation describes which directories to delete for [Adobe Commerce 2.3](https://devdocs.magento.com/guides/v2.3/howdoi/php/php_clear-dirs.html) and [Adobe Commerce 2.4](https://devdocs.magento.com/guides/v2.4/howdoi/php/php_clear-dirs.html).

After you delete these directories, run the following commands:

```bash
php bin/magento setup:upgrade
php bin/magento cache:flush
```

If you're running in production mode, you have to compile and deploy your static assets:

```bash
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
```

If you're running Varnish, you must also restart Varnish after deleting the var/cache/* files. Some browsers also cache Adobe Commerce requests; if you still have caching issues, try a different browser.

## No payment method at checkout {% #payment-method-appear-at-checkout %}

The payment method may not show at checkout for a few possible reasons:

- You're missing the Stripe PHP library or you're using an old version. You can install this dependency by following step 3 of the [installation instructions](/connectors/adobe-commerce/payments/install)
- You have another Stripe module installed that's using an older version of the Stripe PHP library. Disable or uninstall any other active Stripe module.
- You didn't [configure the Stripe API keys](/connectors/adobe-commerce/payments/configuration#general-settings) properly.
- You limited the availability of the payment method to specific countries or currencies.


## Apple Pay or Google Pay not appearing {% #wallet-button %}

If you [configured the Payment Request button](/connectors/adobe-commerce/payments/configuration#express-checkout) and it still doesn't appear, try the following:

- Make sure that you enabled Apple Pay and Google Pay in your [payment methods settings](https://dashboard.stripe.com/settings/payment_methods).
- For Apple Pay, use Safari on an iPhone running iOS 10 and above.
- For Google Pay, use Chrome Desktop or Chrome Mobile with a logged in Google account.
- Make sure that you have at least one card in your Wallet.
  - In iOS, you can add a card by going to **Settings** > **Wallet** > **Apple Pay**.
  - In Chrome, you can add a card by going to **Settings** > **Autofill** > **Payment methods** > **Add new credit card**.
- Confirm that your [iOS device supports Apple Pay](https://stripe.com/apple-pay).
- Confirm that your [Android device supports Google Pay](/stripe-js/elements/payment-request-button).
- Confirm that your [domain is registered and verified](/payments/payment-methods/pmd-registration?dashboard-or-api=dashboard#register-your-domain).
- If your website domain starts with `www`, make sure the domain is `www.example.com` and not `example.com`.
- You must serve your website over HTTPS using a valid {% glossary term="tls" %}TLS{% /glossary %} 1.2 certificate—check this from your browser or from [SSL Labs](https://www.ssllabs.com/ssltest/).
- Make sure that your HTTPS page doesn't load any images, CSS, or JavaScript insecurely. You can check this by clicking the padlock on your browser URL bar.
- Make sure that you enabled the **Wallet** button in the module's configuration section.
- Make sure that you configured a default fallback country (**Stores** > **Configuration** > **General** > **Country Options** > **Default Country**).
- Make sure that you're not using an older Stripe API key. Apple Pay requires a modern API key, which starts with `pk_live_` or `pk_test_`. You can roll your publishable key in the [Developers section](https://dashboard.stripe.com/test/apikeys) of the Dashboard.
- If you're using a OneStepCheckout module, you may additionally need to configure the OSC module to refresh the payment form when guest customers submit their billing address. In most cases, this isn't necessary.

If Apple Pay appears at checkout, but it doesn't appear on the product pages, it may be because of additional reasons:

- You disabled guest checkouts from the Adobe Commerce admin.
- Your website is serving your product pages without a valid TLS 1.2 certificate.
- You overwrote the **Add to Cart** button template in your theme. Try adding some text to `app/code/StripeIntegration/Payments/view/frontend/templates/express/product_button.phtml`. If your product pages remain unchanged, customize your theme and integrate the two templates together.
- JavaScript errors occur when Stripe.js is initializing. Check your browser console for any JavaScript errors related to Stripe.js.

## Pending order stuck {% #order-stuck-pending %}

When creating an order, the initial status is `Pending Payment`, which indicates that the authorization of the payment by the customer's bank is still pending. For all redirect-based payment methods, when an authorization occurs, Stripe notifies your website using {% glossary term="webhook" %}webhooks{% /glossary %}. If your orders don't change from `Pending Payment` to `Processing`, it might indicate that webhooks are missing or incorrect.

Go to your [webhooks settings](https://dashboard.stripe.com/webhooks) to check if a webhook endpoint with your store URL exists. If not, you can try to manually create it by running the following command from your Magento root directory:

```bash
bin/magento stripe:webhooks:configure
```

If the webhook endpoint already exists, check the **Error Rate** to identify the failing webhooks. You can click on the webhook endpoint to see the error messages. To get assistance on webhook issues that aren't due to incorrect server configuration, contact [Stripe Support](https://support.stripe.com) and share details about the errors you encounter.

After fixing the webhook issue, you need to resend the `charge.succeeded` events that weren't delivered correctly to your website. The module provides three commands to resend a single event, a range of events, or events within a date range:

```
bin/magento stripe:webhooks:process-event [-f|--force] <event_id>
bin/magento stripe:webhooks:process-events-range <from_event_id> <to_event_id>
bin/magento stripe:webhooks:process-events-date-range <from_date> [<to_date>]
```

{% callout %}
You can set a full date and time (`2021-12-21 11:22:33+0200`) or use any English textual datetime description (`last Monday`). This function uses your Magento default timezone unless specified otherwise.

See [strtotime](https://www.php.net/strtotime) for all the supported date formats.
{% /callout %}

You can get a list of all failed `charge.succeeded` events in the [Developers section](https://dashboard.stripe.com/events?type=charge.succeeded&delivery_success=false) of your Stripe Dashboard and decide which ones to resend using one of the commands above.

## Error logging and server-side errors (HTTP 500) {% #error-logging %}

Adobe Commerce logs any errors and exceptions it encounters during application runtime in the `var/log` directory. You can find these errors in the following two files:

```
var/log/system.log
var/log/exception.log
```

If you have SSH access, you can filter the error messages with the following command:

```bash
grep -i Stripe var/log/system.log
```

You can display errors live in the console as they occur (or when you refresh a given page). To monitor errors, run the following command to watch the error log:

```bash
tail -f var/log/*
```

If you don't have shell access, you can download this file and search for Stripe errors with a text editor.
