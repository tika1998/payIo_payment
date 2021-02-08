
define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    rendererList.push(
        {
            type: 'payio',
            component: 'PayIo_OfflinePayment/js/view/payment/method-renderer/payiopayment-method'
        }
    );

    /** Add view logic here if needed */
    return Component.extend({});
});
