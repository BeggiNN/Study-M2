<?php

declare(strict_types=1);

namespace Perspective\CheckoutField\Model;

use Perspective\CheckoutField\Api\VatCheckInterface;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

class VatCheck implements VatCheckInterface
{
    /**
     * @param Curl $curl
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Curl $curl,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Validate VAT number via REST API
     *
     * @param string $countryCode
     * @param string $vatNumber
     * @return string
     */
    public function validateVatNumber(string $countryCode, string $vatNumber): string
    {
        if (strlen($countryCode) !== 2 || empty($vatNumber)) {
            return json_encode(['success' => false, 'message' => __('Invalid input parameters.')]);
        }

        $apiUrl = "https://ec.europa.eu/taxation_customs/vies/rest-api/ms/{$countryCode}/vat/{$vatNumber}";

        try {
            $this->curl->get($apiUrl);
            $response = json_decode($this->curl->getBody(), true);

            if (isset($response['isValid']) && $response['isValid']) {
                return json_encode([
                    'success' => true,
                    'valid' => true
                ]);
            } else {
                return json_encode(['success' => true, 'valid' => false, 'message' => __('Invalid VAT number.')]);
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return json_encode(['success' => false, 'message' => __('Error while checking VAT number.')]);
        }
    }
}
