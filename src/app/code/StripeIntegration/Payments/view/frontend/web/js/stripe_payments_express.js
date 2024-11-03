/*global define*/
define(
    [
        'jquery',
        'mage/url',
        'mage/storage',
        'Magento_Ui/js/modal/alert',
        'mage/translate',
        'Magento_Customer/js/customer-data',
        'StripeIntegration_Payments/js/stripe',
        'mage/loader'
    ],
    function (
        jQuery,
        urlBuilder,
        storage,
        alert,
        $t,
        customerData,
        stripe
    ) {
        'use strict';

        return {
            shippingAddress: [],
            shippingMethod: null,
            elements: null,
            expressCheckoutElement: null,
            expressCheckoutOptions: null,
            clickResolvePayload: null,
            resolvePayload: null,
            pendingActionsQueue: [],
            isAddToCartPending: false,
            waitingForNewTotals: false,
            lastTotal: null,
            mountElementId: null,
            mode: null,
            isLoading: false,
            debug: false,

            getExpressCheckoutElementParams: function(payload, callback)
            {
                var serviceUrl = urlBuilder.build('/rest/V1/stripe/payments/ece_params', {}),
                    self = this;

                return storage.post(
                    serviceUrl,
                    JSON.stringify(payload),
                    false
                )
                .fail(function (xhr, textStatus, errorThrown)
                {
                    console.error("Could not retrieve initialization params for Express Checkout");
                })
                .done(function (response) {
                    self.processResponseWithECEParams(response, callback);
                });
            },

            processResponseWithECEParams: function(response, callback)
            {
                try
                {
                    if (typeof response === 'string') {
                        response = JSON.parse(response);
                    }

                    if (response && response.resolvePayload)
                    {
                        if (response.resolvePayload.allowedShippingCountries)
                        {
                            // Convert to object map to array
                            response.resolvePayload.allowedShippingCountries = Object.keys(response.resolvePayload.allowedShippingCountries).map(function (key) { return response.resolvePayload.allowedShippingCountries[key]; });
                        }

                        callback(null, response);
                    }
                    else
                    {
                        callback(null, response);
                    }
                }
                catch (e)
                {
                    callback(e.message, response);
                }
            },

            /**
             * Init Stripe Express
             * @param elementId
             * @param apiKey
             * @param paramsType
             * @param settings
             * @param callback
             */
            initStripeExpress: function (elementId, stripeJsInitParams, locationDetails, expressCheckoutOptions, callback)
            {
                // Only one express element is allowed per page
                if (locationDetails.location == 'minicart')
                {
                    if (document.body.classList.contains('catalog-product-view') // We are on the product page
                        && locationDetails.activeLocations.indexOf('product_page') >= 0) // ECE is enabled on the product page
                        return;

                    if (document.body.classList.contains('checkout-cart-index') // We are on the cart page
                        && locationDetails.activeLocations.indexOf('shopping_cart_page') >= 0) // ECE is enabled on the cart page
                        return;
                }

                if (this.waitingForNewTotals)
                {
                    // This method can be called from cart.subscribe(). In that case, we do not want to re-initialize the widget
                    // because we are waiting for the addToCart() to finish. We will update the amounts after the addToCart() is done.
                    return;
                }

                var self = this;
                this.mountElementId = elementId;
                this.stripeJsInitParams = stripeJsInitParams;
                this.locationDetails = locationDetails;
                this.expressCheckoutOptions = expressCheckoutOptions;

                this.getExpressCheckoutElementParams(locationDetails, function(err, result)
                {
                    if (err)
                    {
                        console.warn('Cannot initialize wallets: ' + err);
                        return;
                    }

                    if (!result.elementOptions)
                        return;

                    if (result.elementOptions.mode != 'setup')
                    {
                        // if (!result.elementOptions.amount || result.elementOptions.amount == 0)
                        //     return;
                    }

                    self.mode = result.elementOptions.mode;
                    self.clickResolvePayload = result.resolvePayload;

                    stripe.initStripe(stripeJsInitParams, function (err)
                    {
                        if (err)
                        {
                            self.showError(self.maskError(err));
                            return;
                        }

                        self.initElements(result.elementOptions);
                        self.initExpressCheckoutElement(elementId, expressCheckoutOptions, callback);
                    });
                });
            },

            // Accepts unlimited parameters
            log: function()
            {
                if (!this.debug)
                    return;

                console.log(...arguments);
            },

            initElements: function(elementsOptions)
            {
                this.log('initElements', elementsOptions);
                if (!this.elements)
                {
                    this.elements = stripe.stripeJs.elements(elementsOptions);
                }
                else
                {
                    this.elements.update(elementsOptions);
                }
            },

            maskError: function(err)
            {
                var errLowercase = err.toLowerCase();
                var pos1 = errLowercase.indexOf("Invalid API key provided".toLowerCase());
                var pos2 = errLowercase.indexOf("No API key provided".toLowerCase());
                if (pos1 === 0 || pos2 === 0)
                    return 'Invalid Stripe API key provided.';

                return err;
            },

            initExpressCheckoutElement: function(elementId, expressCheckoutOptions, callback)
            {
                if (this.expressCheckoutElement)
                {
                    // ECE was already initialized, no need to update it.
                    return;
                }

                var DOMElement = jQuery(elementId),
                    self = this;

                try {
                    if (typeof expressCheckoutOptions === 'string')
                        expressCheckoutOptions = JSON.parse(expressCheckoutOptions);

                    this.expressCheckoutElement = this.elements.create('expressCheckout', expressCheckoutOptions);
                }
                catch (e)
                {
                    console.warn(e.message);
                    return;
                }

                if (document.getElementById(elementId.substr(1)))
                    this.expressCheckoutElement.mount(elementId);

                this.expressCheckoutElement.on('ready', function (result)
                {
                    self.log("on.ready");
                    if (result.availablePaymentMethods)
                    {
                        callback(self.expressCheckoutElement);
                    }
                    else
                    {
                        DOMElement.hide();
                    }
                });
            },

            getClientSecretFromResponse: function(response)
            {
                if (typeof response != "string")
                {
                    return null;
                }

                if (response.indexOf("Authentication Required: ") >= 0)
                {
                    return response.substring("Authentication Required: ".length);
                }

                return null;
            },

            /**
             * Place Order
             * @param result
             * @param callback
             */
            placeOrder: function (result, location, callback) {
                var serviceUrl = urlBuilder.build('/rest/V1/stripe/payments/place_order', {}),
                    payload = {
                        result: result,
                        location: location
                    },
                    self = this;

                return storage.post(
                    serviceUrl,
                    JSON.stringify(payload),
                    false
                ).fail(function (xhr, textStatus, errorThrown)
                {
                    try
                    {
                        var response = JSON.parse(xhr.responseText);

                        var clientSecret = self.getClientSecretFromResponse(response.message);

                        if (clientSecret)
                        {
                            return stripe.authenticateCustomer(clientSecret, function(err)
                            {
                                if (err)
                                    return callback(err, { message: err }, result);

                                self.placeOrder(result, location, callback);
                            });
                        }
                        else
                            callback(response.message, response, result);
                    }
                    catch (e)
                    {
                        return self.showError(xhr.responseText);
                    }
                }).done(function (response) { // @todo - this should be success, we dont want to callback() on failure
                    if (typeof response === 'string')
                    {
                        try
                        {
                            response = JSON.parse(response);
                        }
                        catch (e)
                        {
                            return self.showError(response);
                        }
                    }

                    callback(null, response, result);
                });
            },

            /**
             * Add Item to Cart
             * @param params
             * @param shipping_id
             * @param callback
             */
            addToCart: function(params, shipping_id, callback) {
                var self = this;
                this.isAddToCartPending = this.waitingForNewTotals = true;
                var addToCartPromise = new Promise(function(resolve, reject) {
                    var serviceUrl = urlBuilder.build('/rest/V1/stripe/payments/addtocart', {}),
                        payload = {params: params, shipping_id: shipping_id};

                    storage.post(
                        serviceUrl,
                        JSON.stringify(payload),
                        false
                    ).fail(function(xhr, textStatus, errorThrown) {
                        self.parseFailedResponse.apply(self, [xhr.responseText, callback]);
                        self.waitingForNewTotals = false;
                        reject(); // Reject the promise
                    }).done(function(response) {
                        customerData.invalidate(['cart']);
                        customerData.reload(['cart'], true);
                        callback(null, response);
                        resolve(); // Resolve the promise
                    });
                });

                addToCartPromise.then(function() {
                    self.isAddToCartPending = false;

                    // After the promise is resolved, run the functions in the queue
                    while (self.pendingActionsQueue.length) {
                        var fn = self.pendingActionsQueue.shift(); // Dequeue the first function
                        fn(); // Execute the dequeued function
                    }

                    self.waitingForNewTotals = false;

                    // Invalidate the minicart and display any errors or warnings. The wait is needed so that the server side finishes processing any cart updates
                    setTimeout(function()
                    {
                        customerData.reload(['cart', 'messages'], true);
                    }, 1000);
                });

                return addToCartPromise;
            },

            getShippingAddressFrom: function(eceShippingAddress)
            {
                if (!eceShippingAddress)
                    return null;

                // For some countries like Japan, the ECE does not set the City, only the region
                if (eceShippingAddress.city.length == 0 && eceShippingAddress.region.length > 0)
                    eceShippingAddress.city = eceShippingAddress.region;

                return eceShippingAddress;
            },

            getNewShippingRatesFor: function(address, callback) {
                var serviceUrl = urlBuilder.build('/rest/V1/stripe/payments/ece_shipping_address_changed', {}),
                    payload = {newAddress: address, location: this.locationDetails.location},
                    self = this;

                return storage.post(
                    serviceUrl,
                    JSON.stringify(payload),
                    false
                ).fail(function (xhr, textStatus, errorThrown)
                {
                    self.parseFailedResponse.apply(self, [ xhr.responseText, callback ]);
                }
                ).done(function (response) {
                    self.processResponseWithECEParams(response, callback);
                });
            },

            parseFailedResponse: function(responseText, callback)
            {
                try
                {
                    var response = JSON.parse(responseText);
                    callback(response.message);
                }
                catch (e)
                {
                    callback(responseText);
                }
            },

            /**
             * Apply Shipping and Return Totals
             * @param address
             * @param shipping_id
             * @param callback
             * @returns {*}
             */
            updateShippingRate: function(address, shipping_id, callback) {
                var serviceUrl = urlBuilder.build('/rest/V1/stripe/payments/ece_shipping_rate_changed', {}),
                    payload = {address: address, shipping_id: shipping_id},
                    self = this;

                return storage.post(
                    serviceUrl,
                    JSON.stringify(payload),
                    false
                ).fail(function (xhr, textStatus, errorThrown)
                {
                    self.parseFailedResponse.apply(self, [ xhr.responseText, callback ]);
                }
                ).done(function (response) {
                    self.processResponseWithECEParams(response, callback);
                });
            },

            onShippingAddressChange: function(event)
            {
                this.log("onShippingAddressChange");
                var executeMethod = this.onShippingAddressChangeAction.bind(this, event);

                if (this.isAddToCartPending) {
                    this.pendingActionsQueue.push(executeMethod);
                } else {
                    executeMethod();
                }
            },

            onShippingAddressChangeAction: function(event)
            {
                var self = this;
                this.shippingAddress = this.getShippingAddressFrom(event.address);
                this.waitingForNewTotals = true;
                this.getNewShippingRatesFor(this.shippingAddress, function (err, eceResponseParams)
                {
                    if (err)
                    {
                        event.reject();
                        self.waitingForNewTotals = false;
                        return self.showError(err);
                    }

                    if (!eceResponseParams.resolvePayload.shippingRates)
                    {
                        event.reject();
                        self.waitingForNewTotals = false;
                        return;
                    }

                    self.resolveEvent(event, eceResponseParams.resolvePayload);
                    self.waitingForNewTotals = false;
                });
            },

            resolveEvent: function(event, resolvePayload)
            {
                if (!event)
                    return;

                var self = this;
                var total = this.getLineItemsTotal(resolvePayload.lineItems);

                if (resolvePayload.lineItems && !event.isClick && this.mode != "setup")
                {
                    if (total > 0)
                    {
                        this.log('Updating total to ' + total + ' cents & resolving event with delay', resolvePayload, total);
                        var promise = self.elements.update({amount: total});
                    }
                    else
                    {
                        this.log('Will not update total to 0 cents', resolvePayload, total);
                        delete resolvePayload.lineItems;
                    }

                    // We need this until a promise is implemented by Stripe
                    setTimeout(function() {
                        event.resolve(resolvePayload);
                    }, 1000);
                }
                else
                {
                    if (this.mode == "setup" && resolvePayload.lineItems)
                    {
                        delete resolvePayload.lineItems;
                    }
                    this.log('Resolving event with no delay ', resolvePayload, this.isAddToCartPending, total);
                    event.resolve(resolvePayload);
                }
            },

            getLineItemsTotal: function(lineItems)
            {
                var total = 0;

                if (!lineItems || !lineItems.length)
                    return total;

                for (var i = 0; i < lineItems.length; i++) {
                    total += lineItems[i].amount;
                }

                return total;
            },

            onShippingRateChange: function(event)
            {
                this.log("onShippingRateChange");
                var executeMethod = this.onShippingRateChangeAction.bind(this, event);

                if (this.isAddToCartPending) {
                    this.pendingActionsQueue.push(executeMethod);
                } else {
                    executeMethod();
                }
            },

            onShippingRateChangeAction: function(event)
            {
                var self = this;
                var shippingMethod = event.shippingRate.hasOwnProperty('id') ? event.shippingRate.id : null;
                this.waitingForNewTotals = true;
                this.updateShippingRate(this.shippingAddress, shippingMethod, function (err, response)
                {
                    if (err) {
                        event.reject();
                        self.waitingForNewTotals = false;
                        return self.showError(err);
                    }

                    self.resolveEvent(event, response.resolvePayload);
                    self.waitingForNewTotals = false;
                });
            },

            startLoader: function()
            {
                if (this.isLoading)
                    return;

                this.isLoading = true;
                jQuery('body').trigger('processStart');
            },

            stopLoader: function()
            {
                if (!this.isLoading)
                    return;

                this.isLoading = false;
                jQuery('body').trigger('processStop');
            },

            onConfirm: function(location, confirmResult)
            {
                this.startLoader();

                var onPaymentMethodCreated = this.onPaymentMethodCreated.bind(this, confirmResult, location);
                var showError = this.showError.bind(this);

                var paymentMethodData = {
                    elements: this.elements,
                    params: {
                        billing_details: confirmResult.billingDetails
                    },
                    shipping: confirmResult.shippingAddress
                };

                this.elements.submit().then(function()
                {
                    stripe.stripeJs.createConfirmationToken(paymentMethodData).then(function(createConfirmationTokenResult)
                    {
                        if (createConfirmationTokenResult.error)
                        {
                            return showError(createConfirmationTokenResult.error.message);
                        }
                        else if (createConfirmationTokenResult.confirmationToken)
                        {
                            confirmResult.confirmationToken = createConfirmationTokenResult.confirmationToken;
                            return onPaymentMethodCreated();
                        }
                        else
                        {
                            this.stopLoader();
                            throw new Error('Invalid response from Stripe');
                        }
                    }).catch(function(error) {
                        return showError(error.message);
                    });
                }).catch(function(error) {
                    return showError(error.message);
                });;

            },

            onCancel: function()
            {
                this.log("onCancel");
                this.stopLoader();
            },

            initCheckoutWidget: function (checkoutValidator)
            {
                var self = this;
                this.expressCheckoutElement.on('click', function(event)
                {
                    if (!checkoutValidator())
                    {
                        event.preventDefault();
                        return;
                    }

                    event.isClick = true;
                    self.startLoader();
                    self.resolveEvent(event, self.clickResolvePayload);
                });
                this.expressCheckoutElement.on('shippingaddresschange', this.onShippingAddressChange.bind(this));
                this.expressCheckoutElement.on('shippingratechange', this.onShippingRateChange.bind(this));
                this.expressCheckoutElement.on('confirm', this.onConfirm.bind(this, 'checkout'));
                this.expressCheckoutElement.on('cancel', this.onCancel.bind(this));
            },

            /**
             * Init Widget for Cart Page
             */
            initCartWidget: function ()
            {
                var self = this;
                this.expressCheckoutElement.on('click', function(event) {
                    event.isClick = true;
                    self.startLoader();
                    self.resolveEvent(event, self.clickResolvePayload);
                });
                this.expressCheckoutElement.on('shippingaddresschange', this.onShippingAddressChange.bind(this));
                this.expressCheckoutElement.on('shippingratechange', this.onShippingRateChange.bind(this));
                this.expressCheckoutElement.on('confirm', this.onConfirm.bind(this, 'cart'));
                this.expressCheckoutElement.on('cancel', this.onCancel.bind(this));
            },

            /**
             * Init Widget for Mini cart
             */
            initMiniCartWidget: function ()
            {
                var self = this;
                this.expressCheckoutElement.on('click', function(event) {
                    event.isClick = true;
                    self.startLoader();
                    self.resolveEvent(event, self.clickResolvePayload);
                });
                this.expressCheckoutElement.on('shippingaddresschange', this.onShippingAddressChange.bind(this));
                this.expressCheckoutElement.on('shippingratechange', this.onShippingRateChange.bind(this));
                this.expressCheckoutElement.on('confirm', this.onConfirm.bind(this, 'minicart'));
                this.expressCheckoutElement.on('cancel', this.onCancel.bind(this));
            },

            /**
             * Init Widget for Single Product Page
             */
            initProductWidget: function ()
            {
                var self = this;
                this.expressCheckoutElement.on('click', function(event) {
                    event.isClick = true;
                    self.startLoader();
                    self.onClickAtProductPage(event);
                });
                this.expressCheckoutElement.on('shippingaddresschange', this.onShippingAddressChange.bind(this));
                this.expressCheckoutElement.on('shippingratechange', this.onShippingRateChange.bind(this));
                this.expressCheckoutElement.on('confirm', this.onConfirm.bind(this, 'product'));
                this.expressCheckoutElement.on('cancel', this.onCancel.bind(this));

                this.bindConfigurableProductOptions();
            },

            formToArrayObject: function(data) {
                var obj = {};
                for (var i = 0; i < data.length; i++) {
                    obj[data[i].name] = data[i].value;
                }
                return obj;
            },

            onClickAtProductPage: function(event)
            {
                this.log("onClickAtProductPage");
                var self = this,
                    form = jQuery('#product_addtocart_form'),
                    params = [];

                var validator = form.validation({radioCheckboxClosest: '.nested'});

                if (!validator.valid())
                {
                    this.stopLoader();
                    return;
                }

                // Add to Cart
                params = this.formToArrayObject(form.serializeArray());
                this.addToCart(params, this.shippingMethod, function (err)
                {
                    if (err)
                    {
                        return self.showError(err);
                    }
                });

                this.resolveEvent(event, this.clickResolvePayload);
            },

            showError: function(message)
            {
                this.stopLoader();

                alert({
                    title: $t('Error'),
                    content: message,
                    actions: {
                        always: function (){}
                    }
                });
            },

            onPaymentMethodCreated: function(result, location)
            {
                var self = this;
                this.placeOrder(result, location, function (err, response, result)
                {
                    if (err)
                    {
                        self.showError(response.message);
                    }
                    else if (response.hasOwnProperty('redirect'))
                    {
                        customerData.invalidate(['cart']);
                        window.location = response.redirect;
                    }
                    else
                    {
                        self.stopLoader();
                    }
                });
            },

            bindConfigurableProductOptions: function()
            {
                var self = this;
                var options = jQuery("#product-options-wrapper .configurable select.super-attribute-select");

                options.each(function(index)
                {
                    var onConfigurableProductChanged = self.onConfigurableProductChanged.bind(self, this);
                    jQuery(this).on("change", onConfigurableProductChanged);
                });
            },

            onConfigurableProductChanged: function(element)
            {
                var self = this;

                if (element.value)
                {
                    var locationDetails = {
                        location: 'product',
                        productId: this.locationDetails.productId,
                        attribute: element.value
                    };
                    this.initStripeExpress(
                        this.mountElementId,
                        this.stripeJsInitParams,
                        locationDetails,
                        self.expressCheckoutOptions,
                        self.initProductWidget.bind(self)
                    );
                }
            }
        };
    }
);
