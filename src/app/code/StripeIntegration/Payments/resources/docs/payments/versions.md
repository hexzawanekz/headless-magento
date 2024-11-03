---
title: Adobe Commerce Connector version history
subtitle: Learn about backward compatibility changes in Adobe Commerce Connector versions (Magento 2).
route: /connectors/adobe-commerce/payments/versions
redirects:
  - /connectors/adobe-commerce/versions
stripe_products: []
---

## Version 4.1

Version 4.1.0 introduces changes to handle scenarios where orders could be placed and invoiced without collecting payment. These scenarios include:

- Purchases of trial subscriptions.
- Subscriptions with future start dates.
- Upgrades or downgrades of existing subscription plans.

Previously, the module automatically refunded and closed these orders with an offline credit memo. Now, to improve tax reporting:

- Automatic refunds are deprecated.
- Order items in these scenarios have a custom price of 0.
- These items are excluded from shipping amount calculations.

This results in orders with zero subtotal, shipping, and tax amounts. When regular products are purchased with subscriptions, the subtotal, shipping, and tax amounts only reflect the regular order items. These changes ensure the order grand total matches the collected payment amount on the order date.

Additionally, version 4.1 changes how partial captures and refunds are handled:

- Previously, partial captures or refunds from the Stripe dashboard automatically created invoices or credit memos in Magento.
- These documents didn't include order items, using manual totals or adjustment fees or refunds instead.
- This approach caused issues with tax reporting.

In version 4.1:

- Only full captures and full refunds result in automatic invoices or credit memos.
- For partial captures or refunds, merchants should use the Magento admin panel.
- This ensures correct tax breakdown on documents and accurate reporting to the Stripe API.

