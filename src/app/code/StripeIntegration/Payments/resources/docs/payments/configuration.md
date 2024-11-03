---
title: Configure the Stripe Connector for Adobe Commerce
subtitle: Set up payment methods and other options using the Stripe Connector for Adobe Commerce.
route: /connectors/adobe-commerce/payments/configuration
redirects:
  - /magento/configuration
  - /plugins/magento/configuration
  - /plugins/magento-2/configuration
  - /plugins/adobe-commerce/configuration
stripe_products: []
---

To configure the [Stripe Connector for Adobe Commerce](/connectors/adobe-commerce/payments) navigate to the configuration section for it (**Stores > Configuration > Sales > Payment Methods**):

{% image
   src="images/adobe-commerce/configure-module.png"
   width=70
   ignoreAltTextRequirement=true %}
Configuring the Stripe module
{% /image %}

Stripe appears on your checkout page only after you configure your API keys. If you don't have a Stripe account yet, [register](https://dashboard.stripe.com/register) online.

## Install the Stripe Adobe Commerce app {% #install-the-stripe-adobe-commerce-app %}

Use Stripe Apps to bolster security and simplify the use of distinct restricted keys for each integration with your Stripe account. The process of installing the Stripe App and acquiring the newly generated secret and publishable [keys](/keys) is essential for your integration with the Adobe Commerce connector. This approach eliminates the need to manually create your own restricted key or use a secret key. To integrate the Adobe Commerce app and reinforce your account's security infrastructure:

1. Navigate to the [Stripe App Marketplace](https://marketplace.stripe.com/), then click [Install the Adobe Commerce app](https://marketplace.stripe.com/apps/install/link/com.stripe.AdobeCommerce).
1. Select the Stripe account where you want to install the app.
1. Review and approve the app permissions, install the app in test mode or live mode, then click **Install**.
1. After you install the app, store the keys in a safe place where you won’t lose them. To help yourself remember where you stored it, you can [leave a note on the key in the Dashboard](/keys#reveal-an-api-secret-key-live-mode).
1. Use the newly generated publishable key and secret key to finish the Connector configuration.
1. To manage the app or generate new security keys after installation, navigate to the application settings page in [test mode](https://dashboard.stripe.com/test/settings/apps/com.stripe.AdobeCommerce) or [live mode](https://dashboard.stripe.com/settings/apps/com.stripe.AdobeCommerce).

## General settings {% #general-settings %}

- **Mode:** We recommend that you start by testing the integration in {% glossary term="test-mode" %}test mode{% /glossary %}. Switch to live mode when you're ready to accept live transactions. You can learn more about [testing payments](/testing) on Stripe.
- **API keys:** Fill in the test and live keys that Stripe provides to you in the [Adobe Commerce app](https://dashboard.stripe.com/test/settings/apps/com.stripe.AdobeCommerce).
- **Hold Elevated Risk Orders:** If Stripe {% glossary term="radar" %}Radar{% /glossary %} marks a payment with an `Elevated Risk` status, the module places the order `On Hold` until you review the payment. See the section [Enabling fraud prevention features with Stripe Radar](/connectors/adobe-commerce/payments#radar) for additional details.
- **Receipt Emails:** When enabled, Stripe sends a payment receipt email to the customer after the payment succeeds. You can customize the styles and brand of emails from your Stripe account settings.

## Payments {% #payments %}

- **Enabled:** Enable or disable Stripe as an available payment method for the standard checkout page, for the multi-shipping checkout page, and for the admin area.
- **Payment flow:** Select your preferred payment flow for the standard checkout page. With the embedded payment flow, we embed an iframe-based Payment Element directly in the checkout page. With the redirect payment flow, we redirect customers to Stripe Checkout to complete their payment.
- **Form layout:** Display the payment method selector in Horizontal layout (tabs), or Vertical layout (accordion). We recommend the Vertical layout for narrow sections, such as on mobile or 3-column checkout pages. You can test the two layouts in the PaymentElement's interactive [UI component](/payments/payment-element).
- **Title:** The label you want to display to the customer on the checkout page.
- **Payment method configuration:** Stripe supports [multiple configurations](/payments/payment-method-configurations) of payment methods. After you [configure the payment methods](https://dashboard.stripe.com/settings/payment_methods), they immediately become available in the dropdown field. You can select a different configuration for each of your store views, based on business requirements.
- **Payment Action:** Select a payment mode:
  - **Authorize and Capture**: Charge customer cards immediately after a purchase.
  - **Authorize Only**: Authorize the payment amount and place a hold on the card. You can capture the amount later [by issuing an invoice](/connectors/adobe-commerce/payments/admin#capturing-later).
  - **Order**: Save the customer's payment method without attempting an authorization or capture. You can collect payment for an order processed in this mode by issuing an invoice from the administrative area.
- **Expired authorizations:** For card payments that you don't capture immediately, you must do so within seven days. Any attempt to capture the amount after that returns an error. By enabling this option, the module attempts to recreate the original payment with the original card used for that order. The module saves cards automatically in **Authorize Only** mode and the customer can’t delete them from their account section until you either invoice or cancel the order.
- **Automatic Invoicing:** The Authorize Only option creates a new invoice with a Pending status on checkout. After capturing the charge, the invoice status transitions to Paid. This option is useful when Payment Action is set to Authorize Only: no invoice results from completing the checkout flow. If enabled, the module automatically generates an invoice on checkout completion so you can email it to a customer before charging them.
- **Save customer payment method** Enable this option to allow customers to save their last used payment method in the Stripe vault and reuse it later for quicker checkout.
- **Card Icons:** Display card icons based on the card brands your Stripe account supports.
- **Optional Statement Descriptor:** This is an optional short description for the source of the payment, shown in the customer’s bank statements. If left empty, the default descriptor configured from your Stripe Dashboard applies. This option isn’t available for Multibanco, SEPA Direct Debit, or Sofort.
- **Sort Order:** If you’ve enabled multiple payment methods, this setting determines the order of payment methods presented on the checkout page.

## Express Checkout {% #express-checkout %}

Express Checkout lets customers place orders using one-click wallet buttons like [Link](/payments/link), [Apple Pay](/apple-pay), and [Google Pay](/google-pay). If supported by the customer's device, you can display multiple wallets in any order. Set your preferences in the dedicated configuration section of the Adobe Commerce admin panel.

{% image
   src="images/adobe-commerce/connector-express-checkout.png"
   width=70
   ignoreAltTextRequirement=true %}
Configuration options for Apple Pay and Google Pay
{% /image %}

- **Enabled:** Toggles the wallet button as an available payment method for chosen locations. You can turn it on even if regular payments are disabled.
- **Locations:** Specify the pages where you want the wallet buttons to appear.
- **Seller name:** Your business name, which is displayed in the payment modal.
- **Button height:** You can modify the button height to match the **Add to Cart** and **Proceed to Checkout** buttons in your theme.
- **Sort order:** By default, Stripe arranges wallets in an optimal order based on factors like device capabilities and usage patterns. You can assign a sort order to each wallet in its sub-configuration section by selecting **Use sort order field**.

If you enable Express Checkout and the wallet buttons don't appear, refer to the [troubleshooting page](/connectors/adobe-commerce/payments/troubleshooting#wallet-button).

## Webhooks {% #webhooks %}

Stripe uses webhooks to notify your application when an event happens in your account. Webhooks are particularly useful for updating Magento orders when a customer's bank confirms or declines a payment, or when collecting subscription payments. These events allow the module to mark Magento orders as ready for fulfilment, record refunds against them, or add comments about payment failure reasons.

Starting from version 3 of the module, you no longer need to manually configure webhooks. The module checks and potentially configures webhooks automatically in the following cases:

- When you install or upgrade the module and trigger the `setup:upgrade` command.
- Every time you update the API keys in the Magento admin.
- Every time you change the URL of a store in the Magento admin.
- When the module detects a change in the database during one of the hourly automated checks. This prevents webhooks from being broken due to a manual change to the database, a migration from a different server, or a backup restoration.

When updating webhooks, the module creates a single webhook endpoint per Stripe account. For example, if you have five store views, four are using a Stripe account and the last one is using a different Stripe account, the module creates two webhook endpoints.

This also applies if you use different domain names for your store views. In this case, the module uses one of the store view domains and not your base URL. This is to prevent issues with base URLs often being behind a firewall for security reasons.

The module uses webhook signatures to verify that the events were sent by Stripe, not by a third party. You can disable this protection only when your Magento instance is using developer mode.

{% see-also %}
- [Using Subscriptions](/connectors/adobe-commerce/payments/subscriptions)
- [Using the Adobe Commerce admin panel](/connectors/adobe-commerce/payments/admin)
- [Troubleshooting](/connectors/adobe-commerce/payments/troubleshooting)
{% /see-also %}
