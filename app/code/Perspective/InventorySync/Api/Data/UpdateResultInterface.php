<?php
declare(strict_types=1);

namespace Perspective\InventorySync\Api\Data;

interface UpdateResultInterface
{
    /**
     * Get success status
     *
     * @return bool
     */
    public function getSuccess(): bool;

    /**
     * Set success status
     *
     * @param bool $success
     * @return $this
     */
    public function setSuccess(bool $success);

    /**
     * Get message
     *
     * @return string
     */
    public function getMessage(): string;

    /**
     * Set message
     *
     * @param string $message
     * @return $this
     */
    public function setMessage(string $message);

    /**
     * Get SKU
     *
     * @return string|null
     */
    public function getSku(): ?string;

    /**
     * Set SKU
     *
     * @param string $sku
     * @return $this
     */
    public function setSku(string $sku);

    /**
     * Get quantity
     *
     * @return int|null
     */
    public function getQuantity(): ?int;

    /**
     * Set quantity
     *
     * @param int $quantity
     * @return $this
     */
    public function setQuantity(int $quantity);
}