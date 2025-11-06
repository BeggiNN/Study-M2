<?php
declare(strict_types=1);

namespace Perspective\CheckoutField\Model\Soap;

use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use SoapClient;
use SoapFault;

class VatCheckClient
{
    /**
     * @var string
     */
    private const WSDL_SERVICE_NAME = 'perspectiveCheckoutFieldVatCheckV1';

    /**
     * @var string
     */
    private const SOAP_METHOD = 'perspectiveCheckoutFieldVatCheckV1ValidateVatNumber';

    /**
     * @var SoapClient|null
     */
    private ?SoapClient $soapClient = null;

    /**
     * @param string $baseUrl
     * @param LoggerInterface $logger
     * @param bool $traceEnabled
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly LoggerInterface $logger,
        private readonly bool $traceEnabled = true
    ) {
    }

    /**
     * Validate VAT number via SOAP API
     *
     * @param string $countryCode
     * @param string $vatNumber
     * @return array
     * @throws LocalizedException
     */
    public function validateVatNumber(string $countryCode, string $vatNumber): array
    {
        try {
            $client = $this->getSoapClient();
            $result = $client->{self::SOAP_METHOD}([
                'countryCode' => $countryCode,
                'vatNumber' => $vatNumber
            ]);

            return $this->parseResponse($result);

        } catch (SoapFault $e) {
            $this->logger->error('SOAP VAT validation failed', [
                'country' => $countryCode,
                'error' => $e->getMessage(),
                'request' => $this->getLastRequest(),
                'response' => $this->getLastResponse()
            ]);

            throw new LocalizedException(
                __('VAT validation service is currently unavailable. Please try again later.')
            );
        }
    }

    /**
     * Get SOAP client instance
     *
     * @return SoapClient
     * @throws SoapFault
     */
    private function getSoapClient(): SoapClient
    {
        if ($this->soapClient === null) {
            $wsdlUrl = $this->getWsdlUrl();

            $this->soapClient = new SoapClient($wsdlUrl, [
                'trace' => $this->traceEnabled,
                'exception' => true,
                'cache_wsdl' => WSDL_CACHE_MEMORY
            ]);
        }

        return $this->soapClient;
    }

    /**
     * Get WSDL URL
     *
     * @return string
     */
    private function getWsdlUrl(): string
    {
        return sprintf(
            '%s/soap?wsdl&services=%s',
            rtrim($this->baseUrl, '/'),
            self::WSDL_SERVICE_NAME
        );
    }

    /**
     * Parse SOAP response
     *
     * @param string $response
     * @return array
     */
    private function parseResponse(string $response): array
    {
        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->warning('Invalid JSON response from SOAP API', [
                'response' => $response
            ]);

            return [
                'success' => false,
                'message' => 'Invalid response format'
            ];
        }

        return $decoded;
    }

    /**
     * Get last SOAP request (for debugging)
     *
     * @return string|null
     */
    private function getLastRequest(): ?string
    {
        return $this->soapClient?->__getLastRequest();
    }

    /**
     * Get last SOAP response (for debugging)
     *
     * @return string|null
     */
    private function getLastResponse(): ?string
    {
        return $this->soapClient?->__getLastResponse();
    }

    /**
     * Get available SOAP functions
     *
     * @return array|null
     */
    public function getFunctions(): ?array
    {
        try {
            return $this->getSoapClient()->__getFunctions();
        } catch (SoapFault $e) {
            $this->logger->error('Failed to get SOAP functions', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get available SOAP types
     *
     * @return array|null
     */
    public function getTypes(): ?array
    {
        try {
            return $this->getSoapClient()->__getTypes();
        } catch (SoapFault $e) {
            $this->logger->error('Failed to get SOAP types', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
