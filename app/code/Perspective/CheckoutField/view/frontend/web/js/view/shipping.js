define([
    'jquery',
    'mage/storage',
    'ko',
    'Magento_Checkout/js/model/url-builder',
    'Magento_Checkout/js/model/full-screen-loader',
    'uiRegistry'
], function ($, storage, ko, urlBuilder, fullScreenLoader, registry) {
    'use strict';

    return function (Target) {
        return Target.extend({

            /**
             * Set shipping information handler
             */
            setShippingInformation: function () {
                let self = this;

                if (this.validateShippingInformation()) {
                    let vatNumberField = $('[name="custom_attributes[vat_number]"]');

                    if (!vatNumberField.attr('aria-required')) {
                        this._super();
                    }

                    let countryCode = $('[name="country_id"]').val();
                    let vatNumber = vatNumberField.val();

                    if (countryCode && vatNumber) {
                        fullScreenLoader.startLoader();
                        var originalSuper = this._super.bind(this);
                        storage.post(
                            this.getUrlForVatNumber(countryCode, vatNumber),
                            JSON.stringify({})
                        ).done(function (response) {
                            let result = JSON.parse(response);
                            fullScreenLoader.stopLoader();

                            if (result.success && result.valid) {
                                originalSuper();
                            } else {
                                self.errorValidationMessage(result.message);
                            }
                        }).fail(function () {
                            fullScreenLoader.stopLoader();
                            self.errorValidationMessage('VAT validation failed');
                        });

                    } else {
                        self.errorValidationMessage('VAT number is required.');
                    }
                }
            },

            /**
             * @param {string} countryCode
             * @param {string} vatNumber
             * @return {*}
             */
            getUrlForVatNumber: function (countryCode, vatNumber) {
                return urlBuilder.createUrl('/vat/check/:countryCode/:vatNumber', {
                    countryCode: countryCode,
                    vatNumber: vatNumber
                });
            },
        });
    }
});
