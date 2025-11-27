<?php
declare(strict_types=1);

namespace Perspective\InventorySync\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Perspective\InventorySync\Helper\Data as ConfigHelper;
use Perspective\InventorySync\Model\InventorySyncClient;
use Psr\Log\LoggerInterface;

class ProductSaveAfterObserver implements ObserverInterface
{
    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var InventorySyncClient
     */
    private $syncClient;

    /**
     * @var StockRegistryInterface
     */
    private $stockRegistry;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param ConfigHelper $configHelper
     * @param InventorySyncClient $syncClient
     * @param StockRegistryInterface $stockRegistry
     * @param LoggerInterface $logger
     */
    public function __construct(
        ConfigHelper $configHelper,
        InventorySyncClient $syncClient,
        StockRegistryInterface $stockRegistry,
        LoggerInterface $logger
    ) {
        $this->configHelper = $configHelper;
        $this->syncClient = $syncClient;
        $this->stockRegistry = $stockRegistry;
        $this->logger = $logger;
    }

    /**
     * Execute observer
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        if (!$this->configHelper->isEnabled() || !$this->configHelper->isSyncOnSaveEnabled()) {
            return;
        }

        try {
            /** @var Product $product */
            $product = $observer->getEvent()->getProduct();

            if (!$product || !$product->getSku()) {
                return;
            }

            $stockItem = $this->stockRegistry->getStockItemBySku($product->getSku());
            $quantity = (int) $stockItem->getQty();

            $this->syncClient->syncInventory(
                $product->getSku(),
                $quantity,
                $product->getName(),
                (int) $product->getId()
            );

            if ($this->configHelper->isDebugMode()) {
                $this->logger->info('Product inventory synced', [
                    'sku' => $product->getSku(),
                    'name' => $product->getName(),
                    'quantity' => $quantity
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Product save observer error: ' . $e->getMessage());
        }
    }
}
