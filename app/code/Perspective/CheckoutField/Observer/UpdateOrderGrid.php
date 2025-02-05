<?php

declare(strict_types=1);

namespace Perspective\CheckoutField\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\ResourceConnection;

class UpdateOrderGrid implements ObserverInterface
{
    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private ResourceConnection $resourceConnection
    ) {
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        $vatNumber = $order->getData('vat_number');

        try {
            if ($vatNumber) {
                $connection = $this->resourceConnection->getConnection();
                $tableName = $connection->getTableName('sales_order_grid');

                $connection->update(
                    $tableName,
                    ['vat_number' => $vatNumber],
                    ['entity_id = ?' => $order->getId()]
                );
            }
        } catch (\Exception $exception) {
        }
    }
}
