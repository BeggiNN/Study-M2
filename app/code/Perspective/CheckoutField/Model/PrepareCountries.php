<?php

declare(strict_types=1);

namespace Perspective\CheckoutField\Model;

use Magento\Checkout\Model\ConfigProviderInterface;

class PrepareCountries implements ConfigProviderInterface
{
    /**
     * Additional countries
     */
    private const ADDITIONAL_COUNTRIES = [
        'CH', 'GB', // Switzerland, United Kingdom
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE' // EU countries
    ];

    /**
     * Adding countries for custom field
     *
     * @return array
     */
    public function getConfig(): array
    {
        $additionalVariables['vat_number_countries'] = self::ADDITIONAL_COUNTRIES;

        return $additionalVariables;
    }
}
