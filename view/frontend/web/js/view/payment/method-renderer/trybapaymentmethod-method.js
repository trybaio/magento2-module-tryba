define(
    [
        'Magento_Checkout/js/view/payment/default',
		'Magento_Checkout/js/action/place-order',
		'mage/url',
    ],
    function (Component, placeOrderAction, url) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'WAF_Tryba/payment/trybapaymentmethod'
            },
			afterPlaceOrder: function () {
                window.location.replace(url.build('tryba/redirect/'));
			},
            // Returns send check to info
            getMailingAddress: function() {
                return window.checkoutConfig.payment.checkmo.mailingAddress;
            },
        });
    }
);