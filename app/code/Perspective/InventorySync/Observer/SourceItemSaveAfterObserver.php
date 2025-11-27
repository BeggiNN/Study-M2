<?php
declare(strict_types=1);

namespace Perspective\InventorySync\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Perspective\InventorySync\Helper\Data as ConfigHelper;
use Perspective\InventorySync\Model\InventorySyncClient;
use Psr\Log\LoggerInterface;

class SourceItemSaveAfterObserver implements ObserverInterface
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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param ConfigHelper $configHelper
     * @param InventorySyncClient $syncClient
     * @param LoggerInterface $logger
     */
    public function __construct(
        ConfigHelper $configHelper,
        InventorySyncClient $syncClient,
        LoggerInterface $logger
    ) {
        $this->configHelper = $configHelper;
        $this->syncClient = $syncClient;
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
        if (!$this->configHelper->isEnabled() || !$this->configHelper->isAutoSyncEnabled()) {
            return;
        }

        try {
            /** @var SourceItemInterface $sourceItem */
            $sourceItem = $observer->getEvent()->getData('source_item');

            if (!$sourceItem) {
                return;
            }

            $sku = $sourceItem->getSku();
            $quantity = (int) $sourceItem->getQuantity();

            $this->syncClient->syncInventory($sku, $quantity);

            if ($this->configHelper->isDebugMode()) {
                $this->logger->info('Source item inventory synced', [
                    'sku' => $sku,
                    'quantity' => $quantity,
                    'source_code' => $sourceItem->getSourceCode()
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Source item save observer error: ' . $e->getMessage());
        }
    }
}
