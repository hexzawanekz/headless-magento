---
title: Enable subscriptions for Adobe Commerce products
subtitle: Configure the Stripe Connector for Adobe Commerce to enable subscriptions for any Adobe Commerce product.
route: /connectors/adobe-commerce/payments/subscriptions
redirects:
  - /plugins/magento/subscriptions
  - /plugins/magento-2/subscriptions
  - /plugins/adobe-commerce/subscriptions
  - /connectors/adobe-commerce/subscriptions
stripe_products: []
---

You can turn any [virtual](https://experienceleague.adobe.com/docs/commerce-admin/catalog/products/types/product-create-virtual.html?lang=en) or [simple](https://experienceleague.adobe.com/docs/commerce-admin/catalog/products/types/product-create-simple.html?lang=en) Adobe Commerce product into a subscription product from its configuration page in your admin panel. When a customer buys a subscription product, the module registers a recurring payment against that order using [Stripe Billing](https://stripe.com/billing). Stripe manages this subscription, attempting payment collection on a recurring basis based on the subscription settings in Adobe Commerce. Stripe can also notify the customer if the payment fails and ask them to update their billing details. You can control this in your [Subscriptions and emails settings](https://dashboard.stripe.com/settings/billing/automatic).

On payment success, your website receives a [webhook](/connectors/adobe-commerce/payments/configuration#webhooks) notification from Stripe. The module automatically creates a new order in your Adobe Commerce admin panel for each renewal. These recurring orders don’t include the initial subscription fees, and the module recalculates the shipping and tax amounts for each individual recurring subscription product.

## How to enable and configure subscriptions

You can enable {% glossary term="subscriptions" %}subscriptions{% /glossary %} for any Adobe Commerce product from the product configuration page. When creating or editing a product, scroll down until you see the **Stripe Subscriptions** section:

{% image
   src="images/adobe-commerce/configure-subscriptions.png"
   width=90
   ignoreAltTextRequirement=true %}
Configuration options for subscriptions
{% /image %}

Here, you have the following options:

* **Subscription Enabled:** Turn this on to convert this product into a subscription and automatically create a subscription plan when customers check out with this product. You don’t need to create a subscription plan in your Stripe account. A subscription plan is automatically created for this product when your customers check out.
* **Frequency:** Select **Days**, **Weeks**, **Months**, or **Years**. The customer sees whatever you select here for the frequency. If you prefer to display **30 Days** instead of **1 Month**, set this to **Days** instead. If you select **Days**, the subscription cycle in your Stripe account reflects this.
* **Repeat Every:** Set the length of the billing cycle based on the specified frequency. For example, a value of 30 here with frequency of **Days** bills every 30 days.
* **Trial Days:** Enter the number of days before the first charge for the subscription (that is, the number of free days).
* **Initial Fee:** Enter an amount to charge in addition to the subscription price.
* **Start on specific date:** Enable to expose custom start date specification options. When customers purchase the subscription, it begins on the specified date instead of starting immediately.
* **Pick start date:** Specify the start date for the subscription. The format is a specific date, but the start date forwards to the next applicable billing cycle after the start date has passed. For example, if the start date is `01/01/2024`, a monthly subscription always starts on the 1st of the month, while a 6-month subscription always starts on either January 1st or July 1st.
* **First payment:** Specify how to collect the first payment when you enable a start date:
  * Collect it on the specified start date.
  * Collect the first payment when the order is placed, and all subsequent payments on the specified start date. This option is useful for physical product subscriptions that ship the first product immediately when ordered, with subsequent shipments conforming to the start date of the billing cycle.
* **Prorate first payment:** Setting an immediate first payment exposes this field. Enable this setting to account for the number of days from the date of the order placement to the subscription start date so the first payment reflects the shortened cycle before the full subscription begins.
* **Customers can change subscription:** Enable to allow customers to edit their subscription from the customer account section. They can change the quantity, customizable options, configurable options or bundle options of each order item. These are additional edit options to the shipping address, shipping method or payment method, which customers can always change at any time.
* **Prorate upgrades:** Enable to apply a fee to account for any price difference when a customer upgrades their subscription between billing cycles. Disable to defer the new subscription price until the next billing cycle.
* **Prorate downgrades:** Enable to credit the customer's account balance when they downgrade their subscription mid-cycle and apply the credit to future subscription payments. Disable to apply the lower price to the next billing cycle.

## Configurable subscriptions

You can use Magento [configurable products](https://experienceleague.adobe.com/docs/commerce-admin/catalog/products/types/product-create-configurable.html?lang=en) to offer multiple options to your customers for a single product. Customers can choose their preferred option using either a drop-down, a visual swatch, or a text swatch. Each option can be a simple or virtual product and that product can itself be a subscription.

{% image
   src="images/adobe-commerce/subscription-configurable.png"
   width=50
   ignoreAltTextRequirement=true %}
Configurable subscription
{% /image %}

1. Go to **Stores** > **Attributes** > **Product**.

1. Create an [attribute](https://experienceleague.adobe.com/docs/commerce-admin/catalog/products/types/product-create-configurable.html?lang=en#step-1%3A-choose-the-attributes) and choose your preferred input type and labels.

1. Make sure to set the attribute to be on the **Global** scope.

1. Go to **Stores** > **Attributes** > **Attribute Set** and add the attribute to an attribute set.

1. Add the attribute set to your single products.

1. You can now create a configurable product using the single products updated above.

## Bundled subscriptions

Adobe Commerce allows you to [bundle products](https://experienceleague.adobe.com/docs/commerce-admin/catalog/products/types/product-create-bundle.html?lang=en) when you want to sell multiple products together. This prevents customers from removing an individual product from the cart before checkout.

When a bundle product includes at least one subscription, Stripe treats the price of the entire bundle as the subscription price. For recurring payments, Stripe collects both the amount of the entire bundle item and the individual subscription item of the bundle.

After payment collection, Adobe Commerce automatically creates a recurring order that includes the entire bundle item (the subscription items and regular items from the original order). Inventory is then processed for the subscription and the other products in the bundle. This contrasts with carts that include subscriptions and regular products separately. In that case, recurring orders only include the subscription product.

If you want to combine a subscription product with a regular product and only bill the subscription product in the next cycle, you can use [grouped products](https://experienceleague.adobe.com/docs/commerce-admin/catalog/products/types/product-create-grouped.html?lang=en). Alternatively, you can configure an initial fee for the subscription when there's no inventory to process.

## Switch subscription plans

Customers can change the following from their account:

- Configurable, bundled, simple, and virtual subscriptions
- Customizable or configurable options
- Quantities
- Bundle options
- Shipping address or method
- Payment method

Customers can also switch between two or more plans as long as they belong to the same [configurable product](#configurable-subscriptions).

1. The customer logs into their account and goes to **My subscriptions**.

1. They select the subscription they want to change and click **Change subscription**.

1. The customer is redirected to the subscription product page, where they can change their plan, quantities, or other product options.

1. When they click **Update cart**, they're automatically redirected to the checkout page, where they can review the old and new subscription prices.

1. The customer clicks **Update subscription**, which immediately updates the subscription price or plan. The customer is then redirected to **My subscriptions** in their account.

{% image
   src="images/adobe-commerce/subscription-customer-update.png"
   width=80
   ignoreAltTextRequirement=true %}
Configurable subscription
{% /image %}

You can enable or disable subscription changes for each subscription product separately or you can use the global setting under **Stores** > **Configuration** > **Sales** > **Payment Methods** > **Stripe** > **Subscriptions**.

{% image
   src="images/adobe-commerce/subscription-enable-update-stripe-billing.png"
   width=90
   ignoreAltTextRequirement=true %}
Configurable subscription
{% /image %}

## Prorations for virtual subscriptions

The module supports [prorations](/billing/subscriptions/prorations) for virtual subscriptions. You can enable prorations for upgrades or downgrades separately.

{% image
   src="images/adobe-commerce/subscription-proration.png"
   width=50
   ignoreAltTextRequirement=true %}
Configurable subscription
{% /image %}

The module compares what both the current and new plan cost for a 30-day period to determine whether or not it's an upgrade or downgrade. If the cost of the new plan is lower, we consider it a downgrade. If the price remains the same or if it's higher, we consider it an upgrade.

- For downgrades, we create a refund that we record as a credit memo on the original order.
- For upgrades that require an extra payment, we immediately create a new order with the prorated amount for the remainder of the billing period.
- For upgrades with the same cost, there's no payment or refund and we add a comment to the original order to indicate that the customer changed their subscription.

In all three cases, the module creates the renewal order with the new plan at the next billing cycle.

## Changing customer subscriptions in bulk from the command line {% #changing-customer-subscriptions %}

You can increase or reduce the subscription price for a specific product, or change the shipping cost, product name, or tax rates of an order. To do so, you must migrate existing subscriptions from an old plan to a new one using a CLI command within the Stripe module.

```bash
php bin/magento stripe:subscriptions:migrate-subscription-price <original_product_id> <new_product_id> [<starting_order_id> [<ending_order_id>]]
```

This creates a new order for `new_product_id` as if the customer placed the order during checkout. The billing and shipping details are the same as the initial order, and it uses the same payment method for the subscription.

The module recalculates the order totals based on the new tax rules, shipping method, price changes, and so on. If the original order had any discounts, they also apply to the new order. The total doesn't include any of the initial fees.

Successfully placing the order cancels the original order, including the `original_product_id`. The module adds a comment to the original order mentioning the migration to a new order, and the cancellation of the associated subscription in Stripe. The customer also receives a new order email that tells them their subscription billing details have changed. They can review the new totals in the same email.

If the module can't place the order for any reason, the built-in rollback system cancels the new order creation and leaves the original order intact.

You can use the `original_product_id` as the `new_product_id`, which means that the module only recalculates the order totals. It's possible to migrate from simple subscription products (physical products with a single {% glossary term="sku" %}SKU{% /glossary %}) to virtual subscription products, but not the other way around. This limitation is because physical products require a shipping address but virtual products don't.

The order ID parameters are optional. If they're not specified, the script processes all orders in your website from all store views and all Stripe modes. If you have multiple Stripe accounts configured, the script migrates subscriptions from all Stripe accounts.

## Migrate Stripe Subscriptions from another platform to Adobe Commerce

To migrate subscriptions from another platform, you need to perform the following tasks:

1. Create a mapping between your Adobe Commerce customer IDs and the Stripe customer IDs in the “stripe_customers” database table of Adobe Commerce. You can do this with the following SQL statement in your database:

    ```sql
    INSERT INTO stripe_customers(customer_id, stripe_id, customer_email) VALUES ('2', 'cus_xxxxxxxxxx', 'janedoe@example.com');
    ```

1. Create and configure all subscription products for old orders from the **Subscriptions by Stripe** tab under each product's configuration page:

    {% image
      src="images/adobe-commerce/subscription-fields.png"
      width=50
      ignoreAltTextRequirement=true %}
    Subscription configuration form
    {% /image %}

1. Migrate the orders from your old platform to Adobe Commerce. If you plan on creating them manually from the Adobe Commerce admin area, you can use the **Check / Money Order** payment method so it doesn't collect a live payment. After you finish the order migration, you can replace this payment method with Stripe using the following SQL command:

    ```sql
    UPDATE sales_order_payment SET method='stripe_payments' WHERE method='checkmo';
    ```

1. After creating the orders and products successfully in Adobe Commerce, update the existing Subscriptions in your Stripe account to set the following metadata:

    {% image
      src="images/adobe-commerce/subscription-metadata.png"
      width=50
      ignoreAltTextRequirement=true %}
    Subscription metadata list
    {% /image %}

    {% callout %}
    See also how to [create](/api/subscriptions/create) or [update](/api/subscriptions/update) subscriptions.
    {% /callout %}

1. Test the creation of recurring orders based on subscription renewals:

- Check that you have at least one configured {% glossary term="webhook" %}webhook{% /glossary %} in your Stripe Dashboard under **Developers** > **Webhooks**.
- From your **Stripe Events** section, locate an event that you want to test. The event type must be `invoice.payment_succeeded` and the Invoice must belong to a Subscription.
- From your Magento root directory, trigger the event with the following command: `bin/magento stripe:webhooks:process-event <event_id>`.
- Make sure there were no errors in the console and that the module created a recurring subscription order in Adobe Commerce.

{% see-also %}
- [Use the Adobe Commerce admin panel](/connectors/adobe-commerce/payments/admin)
- [Troubleshooting](/connectors/adobe-commerce/payments/troubleshooting)
{% /see-also %}
