define([], function () {
    'use strict';

    return function (payload) {
        payload.addressInformation['extension_attributes'] = {
            'vat_number': jQuery('[name="custom_attributes[vat_number]"]').val()
        };

        return payload;
    };
});
