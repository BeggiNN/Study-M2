<?php
declare(strict_types=1);

namespace Perspective\InventorySync\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    const XML_PATH_ENABLED = 'inventory_sync/general/enabled';
    const XML_PATH_API_URL = 'inventory_sync/general/api_url';
    const XML_PATH_TIMEOUT = 'inventory_sync/general/timeout';
    const XML_PATH_AUTO_SYNC = 'inventory_sync/general/auto_sync';
    const XML_PATH_SYNC_ON_SAVE = 'inventory_sync/general/sync_on_save';
    const XML_PATH_SYNC_ON_ORDER = 'inventory_sync/general/sync_on_order';
    const XML_PATH_DEBUG_MODE = 'inventory_sync/general/debug_mode';

    /**
     * Check if module is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Laravel API URL
     *
     * @return string
     */
    public function getApiUrl(): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_API_URL,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get API timeout
     *
     * @return int
     */
    public function getTimeout(): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_TIMEOUT,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Check if auto sync is enabled
     *
     * @return bool
     */
    public function isAutoSyncEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_AUTO_SYNC,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Check if sync on save is enabled
     *
     * @return bool
     */
    public function isSyncOnSaveEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SYNC_ON_SAVE,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Check if sync on order is enabled
     *
     * @return bool
     */
    public function isSyncOnOrderEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SYNC_ON_ORDER,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Check if debug mode is enabled
     *
     * @return bool
     */
    public function isDebugMode(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_DEBUG_MODE,
            ScopeInterface::SCOPE_STORE
        );
    }
}
