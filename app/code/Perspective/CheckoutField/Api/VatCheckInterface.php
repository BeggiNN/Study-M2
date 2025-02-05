<?php

declare(strict_types=1);

namespace Perspective\CheckoutField\Api;

interface VatCheckInterface
{
    /**
     * Validate VAT number via VIES API
     *
     * @param string $countryCode
     * @param string $vatNumber
     * @return string
     */
    public function validateVatNumber(string $countryCode, string $vatNumber): string;
}
