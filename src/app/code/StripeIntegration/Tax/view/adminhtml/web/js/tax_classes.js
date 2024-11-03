define([
    'uiLayout'
],
function(layout)
{
    return function(config)
    {
        layout([
            {
                component: 'StripeIntegration_Tax/js/view/ui_components/tax_classes',
                name: 'stripe_tax_classes',
                taxClasses: JSON.parse(config.taxClasses),
                productTaxCodes: JSON.parse(config.productTaxCodes),
                formKey: config.formKey
            }
        ]);

        return config;
    };
});
