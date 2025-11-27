<?php
declare(strict_types=1);

namespace Perspective\InventorySync\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Perspective\InventorySync\Helper\Data as ConfigHelper;
use Perspective\InventorySync\Model\InventorySyncClient;
use Psr\Log\LoggerInterface;

class OrderPlaceAfterObserver implements ObserverInterface
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
        if (!$this->configHelper->isEnabled() || !$this->configHelper->isSyncOnOrderEnabled()) {
            return;
        }

        try {
            /** @var Order $order */
            $order = $observer->getEvent()->getOrder();

            if (!$order) {
                return;
            }

            foreach ($order->getAllVisibleItems() as $item) {
                $sku = $item->getSku();
                $qtyOrdered = (int) $item->getQtyOrdered();

                // Confirm reservation in Laravel microservice
                $this->syncClient->confirmReservation($sku, $qtyOrdered);

                if ($this->configHelper->isDebugMode()) {
                    $this->logger->info('Order inventory confirmed', [
                        'order_id' => $order->getId(),
                        'sku' => $sku,
                        'quantity' => $qtyOrdered
                    ]);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Order place observer error: ' . $e->getMessage());
        }
    }
}
