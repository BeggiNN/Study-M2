<?php
declare(strict_types=1);

namespace Perspective\InventorySync\Api;

interface InventoryUpdateInterface
{
    /**
     * Update product quantity from Laravel
     *
     * @param string $sku
     * @param int $quantity
     * @return mixed[]
     */
    public function updateQuantity(string $sku, int $quantity): array;
}