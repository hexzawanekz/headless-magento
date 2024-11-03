// Copyright © Stripe, Inc
//
// @package    StripeIntegration_Payments
// @version    4.1.6
define(
    [
        'stripejs'
    ],
    function ()
    {
        'use strict';

        // Warning: This file should be kept lightweight as it is loaded on nearly all pages.

        return (window.stripe = {

            // Properties
            version: "4.1.6",
            stripeJs: null,

            initStripe: function(params, callback)
            {
                if (typeof callback == "undefined")
                    callback = null;

                var message = null;

                if (!this.stripeJs)
                {
                    try
                    {
                        var options = {};
                        if (params.options)
                        {
                            options = params.options;
                        }

                        this.stripeJs = Stripe(params.apiKey, options);
                    }
                    catch (e)
                    {
                        if (typeof e != "undefined" && typeof e.message != "undefined")
                            message = 'Could not initialize Stripe.js: ' + e.message;
                        else
                            message = 'Could not initialize Stripe.js';
                    }

                    if (this.stripeJs && typeof params.appInfo != "undefined")
                    {
                        try
                        {
                            this.stripeJs.registerAppInfo(params.appInfo);
                        }
                        catch (e)
                        {
                            console.warn(e);
                        }
                    }
                }

                if (callback)
                    callback(message);
                else if (message)
                    console.error(message);
            },

            authenticateCustomer: function(intentId, done) {
                try {
                    var isPaymentIntent = intentId.startsWith('pi_');
                    var isSetupIntent = intentId.startsWith('seti_');

                    var handleIntent = function(result) {
                        if (result.error)
                            return done(result.error);

                        var intent = result.paymentIntent || result.setupIntent;
                        var requiresActionStatuses = ["requires_action", "requires_source_action"];

                        if (requiresActionStatuses.includes(intent.status))
                        {
                            if (intent.next_action && intent.next_action.type === "verify_with_microdeposits")
                            {
                                window.location = intent.next_action.verify_with_microdeposits.hosted_verification_url;
                            }
                            else
                            {
                                stripe.stripeJs.handleNextAction({
                                    clientSecret: intent.client_secret
                                })
                                .then(function(result)
                                {
                                    if (result && result.error)
                                    {
                                        return done(result.error.message);
                                    }

                                    return done();
                                });
                            }
                        }
                        else
                        {
                            return done();
                        }
                    };

                    if (isPaymentIntent) {
                        this.stripeJs.retrievePaymentIntent(intentId).then(handleIntent);
                    } else if (isSetupIntent) {
                        this.stripeJs.retrieveSetupIntent(intentId).then(handleIntent);
                    } else {
                        throw new Error("Invalid intent ID");
                    }
                } catch (e) {
                    done(e.message);
                }
            }
        });
    }
);
