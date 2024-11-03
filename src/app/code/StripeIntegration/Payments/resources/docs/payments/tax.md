---
title: Automate tax collection and reporting
subtitle: Automate tax collection and reporting with a single integration on Adobe Commerce.
route: /connectors/adobe-commerce/payments/tax
availability: beta
stripe_products: []
---

{% callout type="beta" %}
The Stripe Tax capabilities of our Adobe Commerce payments connector are currently available as a public preview. You can activate them in the payments connector configuration. If you want to use our tax calculation and reporting APIs with other payment solutions, [contact us](mailto:adobe-commerce-stripe-tax-rc@stripe.com).
{% /callout %}

Use the Stripe Tax module for Adobe Commerce to calculate, collect, and report taxes on payments made through any supported Adobe Commerce payment method.

Compared to the native Adobe Commerce tax engine, Stripe Tax provides a simpler configuration and activation process. Because Stripe Tax is dynamic, you don't need to reconfigure Adobe Commerce when tax rates change for specific product types, locations, or time periods. Stripe continuously updates your local tax rules and automatically calculates the correct tax amount.

## Availability

To use Stripe Tax with Adobe Commerce:

- Your business must be based in one of the [supported countries](/tax/supported-countries).
- Your system must meet these minimum requirements: PHP 7.4 or later, Magento 2 version 2.3.7 or later, Adobe Commerce version 2.3.7 or later.

[Contact support](https://support.stripe.com/contact/) if you have questions about setting up Stripe Tax for Adobe Commerce.


{% aside title="Pricing and fees" %}

Usage of the connector's tax capabilities induce [API fees](https://support.stripe.com/questions/understanding-stripe-tax-pricing) on tax calculations and tax recording. In test mode, you can perform 1000 free API calls per day.

{% /aside %}

## Configuration

You can configure the general settings for Stripe Tax in Adobe Commerce on the Tax configuration page.

### General settings
To access your general settings in the admin panel of your Adobe Commerce store:

1. Navigate to **Stores** > **Settings** > **Configuration**.
2. In the Sales section, select **Stripe Tax**.

The following settings are available for configuration:

- **Enabled**: Set to `Yes` to activate Stripe Tax for your store. This enables the module to start calculating, collecting, and reporting taxes.
- **Mode**: Select **Test** to use test credentials for testing the integration without affecting real transactions. Switch to **Live** when you’re ready to calculate taxes in a production environment.
- **Publishable API key**: Enter your publishable API key obtained from your [Stripe Dashboard](https://dashboard.stripe.com/apikeys).
- **Secret API key**: Enter your secret API key from your [Stripe Dashboard](https://dashboard.stripe.com/apikeys).
- **Product prices and promotions**: Configure how to calculate product price taxes and discount amount taxes. Set this to either `Tax exclusive` or `Tax inclusive` depending on your store’s pricing strategy. If you select **Tax exclusive**, product prices configured from the admin panel, or discounts applied on the cart don’t include tax. If you select **Tax inclusive**, the product prices and discounts already include tax.
- **Shipping is**: Determine how shipping is taxed. Select either **Tax free**, **Tax exclusive**, or **Tax inclusive** based on whether and how you want shipping costs to include tax.

### Manage product tax classes

You can manage product tax classes to make sure that your products are categorized correctly for tax calculation purposes.

To add, edit, or remove product tax classes in your Adobe Commerce store:

1. From the admin panel, navigate to **Stores** > **Taxes** > **Stripe Tax Classes** to open the Stripe Tax Classes page where you can manage your tax classes.

1. To add a new tax class, click **Add a new tax class** at the bottom of the Stripe Tax Classes page. This opens a modal where you can select a tax category. Each category represents a specific product tax code. Select the one you want to use for your products, then click **Add**.

1. You can edit an existing Tax Class if your products are already assigned to tax classes from before you installed Stripe Tax. This allows you to keep those existing tax classes, and assign a product tax code to them. To do so, click **Select** in the **Actions** column next to the tax class you want to edit. Choose **Set category** to assign a new category to the tax class.

1. To rename a tax class, click its name. This opens a modal where you can set a new tax class name. Alternatively, you can expand the **Select** options list from the **Actions** column and click **Rename** or **Delete**.

1. Changes to the tax classes aren't saved automatically. To save your changes, click **Save Changes**.

1. After you configure your tax classes, you can use them to apply tax classes on products:
  - From any product edit page, you can configure the product's tax class using the **Tax Class** dropdown. The tax classes you've added appear on the product edit page.
  - Alternatively, you can use the Tax configuration page to set the default tax classes for products when they haven't been assigned a tax class, or when their tax class has been deleted.

### Tax registrations

Before you can perform live tax calculations, you need to add tax registrations in your Stripe Dashboard. You can add tax registrations manually in the [Tax registrations](https://dashboard.stripe.com/test/tax/registrations) section, or through the [Tax thresholds](https://dashboard.stripe.com/test/tax/thresholds) section. During the tax registration steps, you can choose between immediate registration, or schedule the registration to begin at a date in the future if you’re planning to register. Stripe Tax only calculates and collects tax on transactions in these locations.

If you start selling to a new country or tax jurisdiction, Stripe can automatically let you know if you need to register for tax in that jurisdiction. This typically happens when your sales volume exceeds the tax threshold of that tax authority. If you have existing transactions on Stripe, you can use our [monitoring tool](https://dashboard.stripe.com/tax/thresholds) to understand where you might need to register.

## Refunds and tax reversals

A tax reversal is triggered when you create a credit memo from the admin area of Adobe Commerce. The tax reversal will be triggered in both cases of creating an Online credit memo or an Offline credit memo.

- **Refund Offline**: This creates a tax reversal without refunding the payment.
- **Refund**: This creates a tax reversal and refunds the payment online via Stripe.

In cases where the credit memo doesn't include all of the order items (partial credit memos), then the tax reversal is triggered only for the items on the partial credit memo.

{% callout type="caution" %}
The credit memo's "Adjustment Fee" and "Adjustment Refund" fields are never taken into consideration during tax reversals.
{% /callout %}

## Export tax transactions

If you are in a country where automatic tax reporting is not yet supported, you can export your tax transactions from [Tax registrations](https://dashboard.stripe.com/tax/registrations)

- Click on the country for which you'd like to export tax transactions.
- Click the "Export transactions" button at the top right of the registration page.
- Select the date range and columns you'd like to export.
- Click the "Export" button to download the transactions in CSV format.
