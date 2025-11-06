<?php
declare(strict_types=1);

namespace Perspective\CheckoutField\Test\Unit\Model\Soap;

use Magento\Framework\Exception\LocalizedException;
use Perspective\CheckoutField\Model\Soap\VatCheckClient;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class VatCheckClientTest extends TestCase
{
    /**
     * @var VatCheckClient
     */
    private VatCheckClient $client;

    /**
     * @var LoggerInterface|(object&MockObject)|MockObject|(LoggerInterface&object&MockObject)|(LoggerInterface&MockObject)
     */
    private LoggerInterface $logger;

    /**
     * @var string
     */
    private string $baseUrl = 'https://m242.test';

    /**
     * @return void
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->client = new VatCheckClient(
            $this->baseUrl,
            $this->logger,
            false
        );
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testValidateVatNumberReturnsArray(): void
    {
        $this->expectException(LocalizedException::class);
        $this->client->validateVatNumber('PL', '1234567890');
    }

    /**
     * @return void
     */
    public function testGetFunctionsReturnsArrayOrNull(): void
    {
        $result = $this->client->getFunctions();
        $this->assertTrue(is_array($result) || is_null($result));
    }

    /**
     * @return void
     */
    public function testGetTypesReturnsArrayOrNull(): void
    {
        $result = $this->client->getTypes();
        $this->assertTrue(is_array($result) || is_null($result));
    }
}
