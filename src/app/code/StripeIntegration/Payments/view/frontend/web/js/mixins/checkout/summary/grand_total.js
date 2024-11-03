define([
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/totals',
    'Magento_Catalog/js/price-utils',
    'StripeIntegration_Payments/js/view/checkout/trialing_subscriptions',
    'StripeIntegration_Payments/js/view/checkout/summary/prorations'
], function (
    quote,
    totals,
    priceUtils,
    trialingSubscriptions,
    prorations
) {
    'use strict';

    return function (grandTotal)
    {
        return grandTotal.extend(
        {
            totals: quote.getTotals(),

            getValue: function()
            {
                var price = 0;

                if (totals && totals.getSegment('grand_total'))
                {
                    price = parseFloat(totals.getSegment('grand_total').value);
                    price += trialingSubscriptions().getPureValue();
                    price += prorations().getPureValue();
                }

                return grandTotal().getFormattedPrice(price);
            },

            getBaseValue: function () {
                var price = 0;

                if (totals && totals.getSegment('base_grand_total'))
                {
                    price = parseFloat(totals.getSegment('base_grand_total').value);
                    price += trialingSubscriptions().getBasePureValue();
                    price += prorations().getBasePureValue();
                }

                return priceUtils.formatPrice(price, quote.getBasePriceFormat());
            },

            getTaxAmount: function()
            {
                if (totals.getSegment('tax_amount'))
                {
                    // Tax exclusive settings
                    return parseFloat(totals.getSegment('tax_amount').value);
                }

                if (totals.getSegment('tax'))
                {
                    // Tax inclusive settings
                    return parseFloat(totals.getSegment('tax').value);
                }

                // Core implementation should handle both cases
                var total = this.totals();
                if (total && total.tax_amount) {
                    return total.tax_amount;
                }

                return 0;
            },

            getGrandTotalExclTax: function()
            {
                var price = 0;

                if (totals.getSegment('grand_total'))
                {
                    price = parseFloat(totals.getSegment('grand_total').value);
                    price -= parseFloat(this.getTaxAmount());
                    price += trialingSubscriptions().getTaxAmount();
                    price += trialingSubscriptions().getPureValue();
                    price += prorations().getPureValue();
                }

                return grandTotal().getFormattedPrice(price);
            }
        });
    };
});
