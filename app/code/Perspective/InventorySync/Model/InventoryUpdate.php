<?php
declare(strict_types=1);

namespace Perspective\InventorySync\Model;

use Perspective\InventorySync\Api\InventoryUpdateInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Psr\Log\LoggerInterface;

class InventoryUpdate implements InventoryUpdateInterface
{
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var StockRegistryInterface
     */
    private $stockRegistry;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param StockRegistryInterface $stockRegistry
     * @param LoggerInterface $logger
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        StockRegistryInterface $stockRegistry,
        LoggerInterface $logger
    ) {
        $this->productRepository = $productRepository;
        $this->stockRegistry = $stockRegistry;
        $this->logger = $logger;
    }

    /**
     * Update product quantity from Laravel
     *
     * @param string $sku
     * @param int $quantity
     * @return mixed[]
     */
    public function updateQuantity(string $sku, int $quantity): array
    {
        try {
            $product = $this->productRepository->get($sku);
            $stockItem = $this->stockRegistry->getStockItemBySku($sku);

            if (!$stockItem->getItemId()) {
                return [
                    'success' => false,
                    'message' => 'Stock item not found for SKU: ' . $sku
                ];
            }

            $stockItem->setQty($quantity);
            $stockItem->setIsInStock($quantity > 0);
            $this->stockRegistry->updateStockItemBySku($sku, $stockItem);

            $this->logger->info('Inventory updated from Laravel', [
                'sku' => $sku,
                'quantity' => $quantity
            ]);

            return [
                'success' => true,
                'message' => 'Inventory updated successfully',
                'sku' => $sku,
                'quantity' => $quantity
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to update inventory from Laravel: ' . $e->getMessage(), [
                'sku' => $sku,
                'quantity' => $quantity
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}