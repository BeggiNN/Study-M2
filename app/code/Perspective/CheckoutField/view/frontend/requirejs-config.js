var config = {
    'config': {
        'mixins': {
            'Magento_Checkout/js/view/shipping': {
                'Perspective_CheckoutField/js/view/shipping': true
            }
        }
    },
    'map': {
        '*': {
            'Magento_Checkout/js/model/shipping-save-processor/payload-extender': 'Perspective_CheckoutField/js/model/shipping-save-processor/payload-extender'
        }
    }
};
