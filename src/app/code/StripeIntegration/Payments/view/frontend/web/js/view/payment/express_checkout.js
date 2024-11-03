// This file can only be loaded at the checkout page because it injects Magento_Checkout/js/model/quote
define(
    [
        'jquery',
        'uiComponent',
        'StripeIntegration_Payments/js/helper/subscriptions',
        'stripe_payments_express',
        'Magento_Checkout/js/model/quote',
        'Magento_Ui/js/model/messageList'
    ],
    function (
        $,
        Component,
        subscriptions,
        stripeExpress,
        quote,
        globalMessageList
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                // template: 'StripeIntegration_Payments/payment/express_checkout',
                stripePaymentsShowExpressCheckoutSection: false,
                isECErendered: false
            },

            initObservable: function ()
            {
                this._super()
                    .observe([
                        'stripePaymentsShowExpressCheckoutSection',
                        'isExpressCheckoutElementSupported'
                    ]);

                if (subscriptions.isSubscriptionUpdate())
                    return this;

                var self = this;
                var currentTotals = quote.totals();

                quote.totals.subscribe(function (totals)
                {
                    if (JSON.stringify(totals.total_segments) == JSON.stringify(currentTotals.total_segments))
                        return;

                    currentTotals = totals;

                    if (!self.isECErendered)
                        return;

                    self.initECE();
                }, this);

                quote.paymentMethod.subscribe(function(method)
                {
                    if (method != null)
                    {
                        $(".payment-method.stripe-payments.mobile").removeClass("_active");
                    }
                }, null, 'change');

                return this;
            },

            initECE: function()
            {
                if (!this.config().enabled)
                    return;

                var self = this;
                var params = self.config().initParams;
                var payload = {
                    location: 'checkout'
                };
                stripeExpress.initStripeExpress('#payment-request-button', params, payload, self.config().buttonConfig,
                    function () {
                        self.isExpressCheckoutElementSupported(true);
                        self.stripePaymentsShowExpressCheckoutSection(true);
                        stripeExpress.initCheckoutWidget(self.validate.bind(self));
                        self.isECErendered = true;
                    }
                );
            },

            config: function()
            {
                return window.checkoutConfig.payment.express_checkout;
            },

            validate: function()
            {
                var agreementsConfig = window.checkoutConfig ? window.checkoutConfig.checkoutAgreements : {},
                    agreementsInputPath = '.payment-method.stripe-payments.mobile div.checkout-agreements input';
                var isValid = true;

                if (!agreementsConfig.isEnabled || $(agreementsInputPath).length === 0) {
                    return true;
                }

                $(agreementsInputPath).each(function (index, element)
                {
                    if (!$.validator.validateSingleElement(element, {
                        errorElement: 'div',
                        hideError: false
                    })) {
                        isValid = false;
                    }
                });

                return isValid;
            },

            showError: function(message)
            {
                document.getElementById('checkout').scrollIntoView(true);
                globalMessageList.addErrorMessage({ "message": message });
            }
        });
    }
);
