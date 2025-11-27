<?php
declare(strict_types=1);

namespace Perspective\InventorySync\Model;

use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Perspective\InventorySync\Helper\Data as ConfigHelper;
use Psr\Log\LoggerInterface;

class InventorySyncClient
{
    /**
     * @var CurlFactory
     */
    private $curlFactory;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param CurlFactory $curlFactory
     * @param Json $json
     * @param ConfigHelper $configHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        CurlFactory $curlFactory,
        Json $json,
        ConfigHelper $configHelper,
        LoggerInterface $logger
    ) {
        $this->curlFactory = $curlFactory;
        $this->json = $json;
        $this->configHelper = $configHelper;
        $this->logger = $logger;
    }

    /**
     * Sync inventory to Laravel microservice
     *
     * @param string $sku
     * @param int $quantity
     * @param string|null $name
     * @param int|null $productId
     * @return bool
     */
    public function syncInventory(string $sku, int $quantity, ?string $name = null, ?int $productId = null): bool
    {
        if (!$this->configHelper->isEnabled()) {
            $this->log('Inventory sync is disabled');
            return false;
        }

        try {
            $existingInventory = $this->getInventoryBySku($sku);

            if ($existingInventory) {
                return $this->updateInventory($existingInventory['id'], $quantity, $name);
            } else {
                return $this->createInventory($sku, $quantity, $name, $productId);
            }
        } catch (\Exception $e) {
            $this->logger->error('Inventory sync error: ' . $e->getMessage(), [
                'sku' => $sku,
                'quantity' => $quantity,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Get inventory by SKU
     *
     * @param string $sku
     * @return array|null
     */
    public function getInventoryBySku(string $sku): ?array
    {
        try {
            $apiUrl = $this->configHelper->getApiUrl();
            $curl = $this->setupCurl();

            $curl->get($apiUrl . '?search=' . urlencode($sku));
            $response = $curl->getBody();
            $data = $this->json->unserialize($response);

            $this->log('Get inventory response', ['sku' => $sku, 'response' => $data]);

            if (!empty($data['data']) && is_array($data['data'])) {
                foreach ($data['data'] as $item) {
                    if ($item['sku'] === $sku) {
                        return $item;
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            $this->logger->error('Get inventory error: ' . $e->getMessage(), [
                'sku' => $sku
            ]);
            return null;
        }
    }

    /**
     * Create inventory in Laravel
     *
     * @param string $sku
     * @param int $quantity
     * @param string|null $name
     * @param int|null $productId
     * @return bool
     */
    private function createInventory(string $sku, int $quantity, ?string $name = null, ?int $productId = null): bool
    {
        try {
            $apiUrl = $this->configHelper->getApiUrl();
            $curl = $this->setupCurl();

            $data = [
                'sku' => $sku,
                'name' => $name,
                'quantity' => $quantity,
                'reserved_quantity' => 0,
                'magento_product_id' => $productId ?? 0,
                'sync_enabled' => true,
                'low_stock_threshold' => 10
            ];

            $this->log('Creating inventory', $data);

            $curl->addHeader('Content-Type', 'application/json');
            $curl->post($apiUrl, $this->json->serialize($data));

            $status = $curl->getStatus();
            $response = $curl->getBody();

            $this->log('Create inventory response', [
                'status' => $status,
                'response' => $response
            ]);

            return $status >= 200 && $status < 300;
        } catch (\Exception $e) {
            $this->logger->error('Create inventory error: ' . $e->getMessage(), [
                'sku' => $sku,
                'quantity' => $quantity
            ]);
            return false;
        }
    }

    /**
     * Update inventory in Laravel
     *
     * @param int $inventoryId
     * @param int $quantity
     * @param string|null $name
     * @return bool
     */
    private function updateInventory(int $inventoryId, int $quantity, ?string $name = null): bool
    {
        try {
            $apiUrl = $this->configHelper->getApiUrl();
            $curl = $this->setupCurl();

            $data = [
                'quantity' => $quantity
            ];

            if ($name !== null) {
                $data['name'] = $name;
            }

            $this->log('Updating inventory', ['id' => $inventoryId, 'data' => $data]);

            $curl->addHeader('Content-Type', 'application/json');
            $curl->setOption(CURLOPT_CUSTOMREQUEST, 'PUT');
            $curl->post($apiUrl . '/' . $inventoryId, $this->json->serialize($data));

            $status = $curl->getStatus();
            $response = $curl->getBody();

            $this->log('Update inventory response', [
                'status' => $status,
                'response' => $response
            ]);

            return $status >= 200 && $status < 300;
        } catch (\Exception $e) {
            $this->logger->error('Update inventory error: ' . $e->getMessage(), [
                'inventory_id' => $inventoryId,
                'quantity' => $quantity
            ]);
            return false;
        }
    }

    /**
     * Add stock to inventory
     *
     * @param string $sku
     * @param int $quantity
     * @return bool
     */
    public function addStock(string $sku, int $quantity): bool
    {
        try {
            $inventory = $this->getInventoryBySku($sku);
            if (!$inventory) {
                $this->log('Inventory not found for SKU: ' . $sku);
                return false;
            }

            $apiUrl = $this->configHelper->getApiUrl();
            $curl = $this->setupCurl();

            $data = ['quantity' => $quantity];

            $curl->addHeader('Content-Type', 'application/json');
            $curl->post(
                $apiUrl . '/' . $inventory['id'] . '/add-stock',
                $this->json->serialize($data)
            );

            $status = $curl->getStatus();
            return $status >= 200 && $status < 300;
        } catch (\Exception $e) {
            $this->logger->error('Add stock error: ' . $e->getMessage(), [
                'sku' => $sku,
                'quantity' => $quantity
            ]);
            return false;
        }
    }

    /**
     * Reserve inventory
     *
     * @param string $sku
     * @param int $quantity
     * @return bool
     */
    public function reserveInventory(string $sku, int $quantity): bool
    {
        try {
            $inventory = $this->getInventoryBySku($sku);
            if (!$inventory) {
                $this->log('Inventory not found for SKU: ' . $sku);
                return false;
            }

            $apiUrl = $this->configHelper->getApiUrl();
            $curl = $this->setupCurl();

            $data = ['quantity' => $quantity];

            $curl->addHeader('Content-Type', 'application/json');
            $curl->post(
                $apiUrl . '/' . $inventory['id'] . '/reserve',
                $this->json->serialize($data)
            );

            $status = $curl->getStatus();
            return $status >= 200 && $status < 300;
        } catch (\Exception $e) {
            $this->logger->error('Reserve inventory error: ' . $e->getMessage(), [
                'sku' => $sku,
                'quantity' => $quantity
            ]);
            return false;
        }
    }

    /**
     * Confirm reservation (after successful order)
     *
     * @param string $sku
     * @param int $quantity
     * @return bool
     */
    public function confirmReservation(string $sku, int $quantity): bool
    {
        try {
            $inventory = $this->getInventoryBySku($sku);
            if (!$inventory) {
                $this->log('Inventory not found for SKU: ' . $sku);
                return false;
            }

            $apiUrl = $this->configHelper->getApiUrl();
            $curl = $this->setupCurl();

            $data = ['quantity' => $quantity];

            $curl->addHeader('Content-Type', 'application/json');
            $curl->post(
                $apiUrl . '/' . $inventory['id'] . '/confirm',
                $this->json->serialize($data)
            );

            $status = $curl->getStatus();
            return $status >= 200 && $status < 300;
        } catch (\Exception $e) {
            $this->logger->error('Confirm reservation error: ' . $e->getMessage(), [
                'sku' => $sku,
                'quantity' => $quantity
            ]);
            return false;
        }
    }

    /**
     * Setup CURL with timeout and headers
     *
     * @return \Magento\Framework\HTTP\Client\Curl
     */
    private function setupCurl()
    {
        $curl = $this->curlFactory->create();
        $timeout = $this->configHelper->getTimeout();
        $curl->setTimeout($timeout);
        $curl->addHeader('Accept', 'application/json');

        return $curl;
    }

    /**
     * Log message if debug mode is enabled
     *
     * @param string $message
     * @param array $context
     */
    private function log(string $message, array $context = []): void
    {
        if ($this->configHelper->isDebugMode()) {
            $this->logger->info('InventorySync: ' . $message, $context);
        }
    }
}