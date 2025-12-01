<?php
declare(strict_types=1);

namespace Perspective\CategoryImport\Model\Export;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\ImportExport\Model\Export\AbstractEntity;
use Magento\ImportExport\Model\Export\Factory as ExportFactory;
use Magento\ImportExport\Model\ResourceModel\CollectionByPagesIteratorFactory;
use Magento\Store\Model\StoreManagerInterface;

class Category extends AbstractEntity
{
    public const ENTITY_CODE = 'catalog_category';

    /**
     * @var CollectionFactory
     */
    protected $categoryCollectionFactory;

    /**
     * @var EavConfig
     */
    protected $eavConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param ExportFactory $collectionFactory
     * @param CollectionByPagesIteratorFactory $resourceColFactory
     * @param CollectionFactory $categoryCollectionFactory
     * @param EavConfig $eavConfig
     * @param array $data
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        ExportFactory $collectionFactory,
        CollectionByPagesIteratorFactory $resourceColFactory,
        CollectionFactory $categoryCollectionFactory,
        EavConfig $eavConfig,
        array $data = []
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->eavConfig = $eavConfig;
        parent::__construct($scopeConfig, $storeManager, $collectionFactory, $resourceColFactory, $data);
    }

    /**
     * Export process
     *
     * @return string
     */
    public function export()
    {
        $this->_prepareEntityCollection($this->_getEntityCollection());
        $writer = $this->getWriter();

        $page = 0;
        while (true) {
            ++$page;
            $entityCollection = $this->_getEntityCollection(true);
            $entityCollection->setOrder('entity_id', 'ASC');
            $entityCollection->setStoreId(\Magento\Store\Model\Store::DEFAULT_STORE_ID);
            $entityCollection->setPage($page, $this->_pageSize);

            if ($entityCollection->count() == 0) {
                break;
            }

            $exportData = $this->getExportData($entityCollection);

            if ($page == 1) {
                $writer->setHeaderCols($this->_getHeaderColumns());
            }

            foreach ($exportData as $dataRow) {
                $writer->writeRow($dataRow);
            }

            if ($entityCollection->getCurPage() >= $entityCollection->getLastPageNumber()) {
                break;
            }
        }

        return $writer->getContents();
    }

    /**
     * Get export data for collection
     *
     * @param \Magento\Catalog\Model\ResourceModel\Category\Collection $collection
     * @return array
     */
    protected function getExportData($collection)
    {
        $exportData = [];

        foreach ($collection as $category) {
            // Skip root category
            if ($category->getId() == 1) {
                continue;
            }

            $exportData[] = $this->exportItem($category);
        }

        return $exportData;
    }

    /**
     * Export item
     *
     * @param \Magento\Framework\Model\AbstractModel $item
     * @return array
     */
    public function exportItem($item)
    {
        return [
            'entity_id' => $item->getId(),
            'name' => $item->getName(),
            'parent_id' => $item->getParentId(),
            'path' => $item->getPath(),
            'position' => $item->getPosition(),
            'level' => $item->getLevel(),
            'is_active' => $item->getIsActive(),
            'url_key' => $item->getUrlKey(),
            'description' => $item->getDescription(),
            'meta_title' => $item->getMetaTitle(),
            'meta_keywords' => $item->getMetaKeywords(),
            'meta_description' => $item->getMetaDescription(),
            'include_in_menu' => $item->getIncludeInMenu(),
            'display_mode' => $item->getDisplayMode(),
        ];
    }

    /**
     * Get header columns
     *
     * @return array
     */
    protected function _getHeaderColumns()
    {
        return [
            'entity_id',
            'name',
            'parent_id',
            'path',
            'position',
            'level',
            'is_active',
            'url_key',
            'description',
            'meta_title',
            'meta_keywords',
            'meta_description',
            'include_in_menu',
            'display_mode',
        ];
    }

    /**
     * Entity type code getter
     *
     * @return string
     */
    public function getEntityTypeCode()
    {
        return self::ENTITY_CODE;
    }

    /**
     * Get entity collection
     *
     * @param bool $resetCollection
     * @return \Magento\Catalog\Model\ResourceModel\Category\Collection
     */
    protected function _getEntityCollection($resetCollection = false)
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect('*');
        return $collection;
    }

    /**
     * Prepare entity collection
     *
     * @param \Magento\Catalog\Model\ResourceModel\Category\Collection $collection
     * @return \Magento\Catalog\Model\ResourceModel\Category\Collection
     */
    protected function _prepareEntityCollection($collection)
    {
        return $collection;
    }

    /**
     * Get attribute collection
     *
     * @return \Magento\Eav\Model\ResourceModel\Entity\Attribute\Collection
     */
    public function getAttributeCollection()
    {
        return $this->eavConfig
            ->getEntityType(self::ENTITY_CODE)
            ->getAttributeCollection();
    }

    /**
     * Filter attribute collection
     *
     * @param \Magento\Framework\Data\Collection $collection
     * @return \Magento\Framework\Data\Collection
     */
    public function filterAttributeCollection(\Magento\Framework\Data\Collection $collection)
    {
        if ($collection instanceof \Magento\Eav\Model\ResourceModel\Entity\Attribute\Collection) {
            $validAttributes = $this->_getHeaderColumns();
            $collection->addFieldToFilter('attribute_code', ['in' => $validAttributes]);
        }
        return $collection;
    }

    /**
     * Filter entity collection
     *
     * @param \Magento\Framework\Data\Collection $collection
     * @return \Magento\Framework\Data\Collection
     */
    public function filterEntityCollection(\Magento\Framework\Data\Collection $collection)
    {
        return $collection;
    }
}
