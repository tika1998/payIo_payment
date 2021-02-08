
/*browser:true*/
/*global define*/
define(
    [
        'ko',
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'PayIo_OfflinePayment/js/action/set-payment-method-action'
    ],
    function (ko, $, Component, setPaymentMethodAction) {
        'use strict';
        return Component.extend({
            defaults: {
                redirectAfterPlaceOrder: false,
                template: 'PayIo_OfflinePayment/payment/payiopayment-form'
            },
         
            afterPlaceOrder: function () { 
                setPaymentMethodAction(this.messageContainer);
                return false;
            }
        });
    }
);



