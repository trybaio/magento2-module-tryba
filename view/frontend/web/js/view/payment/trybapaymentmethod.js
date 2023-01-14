define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'tryba',
                component: 'WAF_Tryba/js/view/payment/method-renderer/trybapaymentmethod-method'
            }
        );
        // Add view logic here if needed
        return Component.extend({});
    }
);