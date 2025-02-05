<?php

declare(strict_types=1);

namespace Perspective\CheckoutField\Plugin\Block\Checkout;

use Magento\Checkout\Block\Checkout\LayoutProcessor;

class LayoutProcessorPlugin
{
    public const VAT_NUMBER_ATTRIBUTE_CODE = 'vat_number';

    /**
     * @param LayoutProcessor $subject
     * @param array $jsLayout
     * @return array
     */
    public function afterProcess(
        LayoutProcessor $subject,
        array  $jsLayout
    ): array {
        $jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']
        ['shippingAddress']['children']['shipping-address-fieldset']['children'][self::VAT_NUMBER_ATTRIBUTE_CODE] = [
            'component' => 'Perspective_CheckoutField/js/form/element/vat-number',
            'config' => [
                'customScope' => 'shippingAddress.custom_attributes',
                'template' => 'ui/form/field',
                'elementTmpl' => 'ui/form/element/input'
            ],
            'dataScope' => 'shippingAddress.custom_attributes.' . self::VAT_NUMBER_ATTRIBUTE_CODE,
            'label' => __('VAT Number'),
            'provider' => 'checkoutProvider',
            'visible' => true,
            'validation' => ['required-entry' => true],
            'sortOrder' => 500
        ];

        return $jsLayout;
    }
}
