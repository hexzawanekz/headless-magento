define(
    [
    'jquery',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/url-builder',
    'mage/storage',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/action/place-order',
    'Magento_Checkout/js/action/select-payment-method',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/model/payment/additional-validators',
    'mage/url',
    'ko'
    ],
    function (
        $,
        quote,
        urlBuilder,
        storage,
        customerData,
        Component,
        placeOrderAction,
        selectPaymentMethodAction,
        customer,
        checkoutData,
        additionalValidators,
        url, ko
        ) {
        'use strict';

        self.specializationArray = ko.observableArray();
        
        if(customer.isLoggedIn()){
            $.ajax({
                type: "POST",
                url: url.build('p2c2p/token/index'),
                data: {userId : customer.customerData.id },
                async : false,
                success: function (response) {                    
                    self.specializationArray(response.items);
                },
                error: function (response) {
                    self.specializationArray(response);
                }
            });
        }

        return Component.extend({
            defaults: {
                template: 'P2c2p_P2c2pPayment/payment/P2c2pPayment'
            },

            tokenChangeEvent : function(){                
                var token_options = $('#' +this.getCode() +'_test1');
                var btnTokenId =  $('#' +this.getCode() +'_btnTokenDelete').selector;

                if(token_options.val() === "")
                    $(btnTokenId).hide();
                else
                    $(btnTokenId).show();
            },

            removeStoredCard : function(){

                var form_id = $('#' +this.getCode() +'_form').selector;
                var token_options = $('#' +this.getCode() +'_test1');
                var btnTokenId =  $('#' +this.getCode() +'_btnTokenDelete').selector;  

                var tokenId = token_options.val();

                if(tokenId === ""){
                    alert("Please select stored card Id.");
                    return;
                }

                if(!confirm("Are you sure you want to delete?")) return;

                $.ajax({
                    type: "POST",
                    url: url.build('p2c2p/token/remove'),
                    data: {token: tokenId},
                    async : false,
                    success: function (response) {
                        if(response === "0"){
                            alert("Unable to remove your card. Please try again, and let us know if the problem persists.");
                            return;
                        }

                        var isdeleted = $(token_options.selector +" option[value="+ tokenId + "]").remove();                        

                        if($(token_options.selector).find("option").length <= 1){
                            $(form_id).remove();
                        }

                        if(isdeleted.length === 0){
                            alert("Unable to remove your card. Please try again, and let us know if the problem persists.")
                        }
                        else{
                            $(btnTokenId).hide();
                            alert("Your card has been removed successfully.");  
                        }
                    },
                    error: function (response) {
                        alert("Unable to remove your card. Please try again, and let us know if the problem persists.");
                        return;
                    }
                });
            },           
            placeOrder: function (data, event) {
                if (event) {
                    event.preventDefault();
                }
                var self = this,
                placeOrder,
                emailValidationResult = customer.isLoggedIn(),
                loginFormSelector = 'form[data-role=email-with-possible-login]';
                if (!customer.isLoggedIn()) {
                    $(loginFormSelector).validation();
                    emailValidationResult = Boolean($(loginFormSelector + ' input[name=username]').valid());
                }
                if (emailValidationResult && this.validate() && additionalValidators.validate()) {
                    this.isPlaceOrderActionAllowed(false);
                    placeOrder = placeOrderAction(this.getData(), false, this.messageContainer);

                    $.when(placeOrder).fail(function () {
                        self.isPlaceOrderActionAllowed(true);
                    }).done(this.afterPlaceOrder.bind(this));
                    return true;
                }
                return false;
            },
            getData: function() {                
                return {
                    'method': this.item.method,
                    'additional_data': {
                        'test1': $('#' +this.getCode() +'_test1').val(),
                    }
                }
            },
            selectPaymentMethod: function () {
                selectPaymentMethodAction(this.getData());
                checkoutData.setSelectedPaymentMethod(this.item.method);
                return true;
            },

            afterPlaceOrder: function () {
                window.location.replace(url.build('p2c2p/payment/request'));
            }
        });
    }
);