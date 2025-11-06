<?php
declare(strict_types=1);

namespace Perspective\CheckoutField\Model\Soap;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\ObjectManagerInterface;
use Psr\Log\LoggerInterface;

class VatCheckClientFactory
{
    private const XML_PATH_BASE_URL = 'web/unsecure/base_url';

    public function __construct(
        private readonly ObjectManagerInterface $objectManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Create SOAP client instance
     *
     * @param string|null $baseUrl
     * @param bool $traceEnabled
     * @return VatCheckClient
     */
    public function create(?string $baseUrl = null, bool $traceEnabled = true): VatCheckClient
    {
        if ($baseUrl === null) {
            $baseUrl = $this->scopeConfig->getValue(self::XML_PATH_BASE_URL);
        }

        return $this->objectManager->create(VatCheckClient::class, [
            'baseUrl' => $baseUrl,
            'logger' => $this->logger,
            'traceEnabled' => $traceEnabled
        ]);
    }
}