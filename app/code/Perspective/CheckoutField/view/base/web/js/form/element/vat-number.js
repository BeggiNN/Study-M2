/**
 * @api
 */
define([
    'underscore',
    'Magento_Ui/js/form/element/abstract'
], function (_, Abstract) {
    'use strict';

    return Abstract.extend({
        defaults: {
            imports: {
                countryOptions: '${ $.parentName }.country_id:indexedOptions',
                update: '${ $.parentName }.country_id:value'
            }
        },

        /**
         * Initializes observable properties of instance
         *
         * @returns {Abstract} Chainable.
         */
        initObservable: function () {
            this._super();

            /**
             * equalityComparer function
             *
             * @returns boolean.
             */
            this.value.equalityComparer = function (oldValue, newValue) {
                return !oldValue && !newValue || oldValue === newValue;
            };

            return this;
        },

        /**
         * Method called every time country selector's value gets changed.
         * Updates all validations and requirements for certain country.
         * @param {String} value - Selected country ID.
         */
        update: function (value) {
            var isVatNumberRequired = false,
                option;

            if (!value) {
                return;
            }

            option = _.isObject(this.countryOptions) && this.countryOptions[value];

            if (!option) {
                return;
            }

            let countries = window.checkoutConfig.vat_number_countries;
            if (countries !== undefined) {
                isVatNumberRequired = !!countries.includes(value);
            }
            this.setValidation({'required-entry': isVatNumberRequired})
            this.required(isVatNumberRequired);
        }
    });

});
