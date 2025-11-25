<?php
declare(strict_types=1);

namespace Perspective\CategoryImport\Model\Import;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Framework\Stdlib\StringUtils;
use Magento\ImportExport\Helper\Data as ImportHelper;
use Magento\ImportExport\Model\Import;
use Magento\ImportExport\Model\Import\Entity\AbstractEntity;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Magento\ImportExport\Model\ResourceModel\Helper;
use Magento\ImportExport\Model\ResourceModel\Import\Data;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Category extends AbstractEntity
{
    public const ENTITY_CODE = 'catalog_category';
    public const COL_NAME = 'name';
    public const COL_PARENT_ID = 'parent_id';
    public const COL_PATH = 'path';
    public const COL_POSITION = 'position';
    public const COL_LEVEL = 'level';
    public const COL_IS_ACTIVE = 'is_active';
    public const COL_URL_KEY = 'url_key';
    public const COL_DESCRIPTION = 'description';
    public const COL_META_TITLE = 'meta_title';
    public const COL_META_KEYWORDS = 'meta_keywords';
    public const COL_META_DESCRIPTION = 'meta_description';
    public const COL_INCLUDE_IN_MENU = 'include_in_menu';
    public const COL_DISPLAY_MODE = 'display_mode';
    public const COL_AVAILABLE_SORT_BY = 'available_sort_by';
    public const COL_DEFAULT_SORT_BY = 'default_sort_by';
    public const COL_STORE_ID = 'store_id';
    public const COL_ENTITY_ID = 'entity_id';

    protected $needColumnCheck = true;
    protected $logInHistory = true;
    protected $_permanentAttributes = [
        self::COL_NAME,
        self::COL_PARENT_ID,
        self::COL_PATH,
        self::COL_POSITION,
        self::COL_LEVEL,
        self::COL_IS_ACTIVE,
    ];

    protected $_validColumnNames = [
        self::COL_ENTITY_ID,
        self::COL_NAME,
        self::COL_PARENT_ID,
        self::COL_PATH,
        self::COL_POSITION,
        self::COL_LEVEL,
        self::COL_IS_ACTIVE,
        self::COL_URL_KEY,
        self::COL_DESCRIPTION,
        self::COL_META_TITLE,
        self::COL_META_KEYWORDS,
        self::COL_META_DESCRIPTION,
        self::COL_INCLUDE_IN_MENU,
        self::COL_DISPLAY_MODE,
        self::COL_AVAILABLE_SORT_BY,
        self::COL_DEFAULT_SORT_BY,
        self::COL_STORE_ID,
    ];

    private CategoryFactory $categoryFactory;
    private CategoryRepositoryInterface $categoryRepository;
    private CollectionFactory $categoryCollectionFactory;
    private StoreManagerInterface $storeManager;
    private LoggerInterface $logger;
    private array $categoriesCache = [];

    public function __construct(
        JsonHelper $jsonHelper,
        ImportHelper $importExportData,
        Data $importData,
        Config $config,
        ResourceConnection $resource,
        Helper $resourceHelper,
        StringUtils $string,
        ProcessingErrorAggregatorInterface $errorAggregator,
        CategoryFactory $categoryFactory,
        CategoryRepositoryInterface $categoryRepository,
        CollectionFactory $categoryCollectionFactory,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->categoryFactory = $categoryFactory;
        $this->categoryRepository = $categoryRepository;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->storeManager = $storeManager;
        $this->logger = $logger;

        parent::__construct(
            $jsonHelper,
            $importExportData,
            $importData,
            $config,
            $resource,
            $resourceHelper,
            $string,
            $errorAggregator
        );
    }

    public function getEntityTypeCode(): string
    {
        return self::ENTITY_CODE;
    }

    public function validateRow(array $rowData, $rowNum): bool
    {
        if (isset($this->_validatedRows[$rowNum])) {
            return !$this->getErrorAggregator()->isRowInvalid($rowNum);
        }

        $this->_validatedRows[$rowNum] = true;

        // Check required fields
        if (empty($rowData[self::COL_NAME])) {
            $this->addRowError('CategoryNameIsRequired', $rowNum);
            return false;
        }

        if ($this->getBehavior() === Import::BEHAVIOR_DELETE) {
            if (empty($rowData[self::COL_ENTITY_ID]) && empty($rowData[self::COL_PATH])) {
                $this->addRowError('EntityIdOrPathRequired', $rowNum);
                return false;
            }
        }

        return !$this->getErrorAggregator()->isRowInvalid($rowNum);
    }

    protected function _importData(): bool
    {
        if (Import::BEHAVIOR_DELETE === $this->getBehavior()) {
            $this->deleteCategories();
        } elseif (Import::BEHAVIOR_REPLACE === $this->getBehavior()) {
            $this->replaceCategories();
        } elseif (Import::BEHAVIOR_APPEND === $this->getBehavior()) {
            $this->saveCategories();
        }

        return true;
    }

    protected function saveCategories(): void
    {
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            foreach ($bunch as $rowNum => $rowData) {
                if (!$this->validateRow($rowData, $rowNum)) {
                    continue;
                }

                try {
                    $this->saveCategory($rowData);
                } catch (\Exception $e) {
                    $this->logger->error('Category import error: ' . $e->getMessage());
                    $this->addRowError($e->getMessage(), $rowNum);
                }
            }
        }
    }

    protected function replaceCategories(): void
    {
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            foreach ($bunch as $rowNum => $rowData) {
                if (!$this->validateRow($rowData, $rowNum)) {
                    continue;
                }

                try {
                    $category = $this->findExistingCategory($rowData);
                    if ($category) {
                        $this->updateCategory($category, $rowData);
                    } else {
                        $this->saveCategory($rowData);
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Category replace error: ' . $e->getMessage());
                    $this->addRowError($e->getMessage(), $rowNum);
                }
            }
        }
    }

    protected function deleteCategories(): void
    {
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            foreach ($bunch as $rowNum => $rowData) {
                try {
                    $category = $this->findExistingCategory($rowData);
                    if ($category && $category->getId()) {
                        $this->categoryRepository->delete($category);
                        $this->countItemsDeleted++;
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Category delete error: ' . $e->getMessage());
                    $this->addRowError($e->getMessage(), $rowNum);
                }
            }
        }
    }

    protected function saveCategory(array $rowData): void
    {
        $category = $this->categoryFactory->create();

        $storeId = !empty($rowData[self::COL_STORE_ID])
            ? (int)$rowData[self::COL_STORE_ID]
            : \Magento\Store\Model\Store::DEFAULT_STORE_ID;
        $category->setStoreId($storeId);

        $category->setName($rowData[self::COL_NAME]);

        $parentId = !empty($rowData[self::COL_PARENT_ID])
            ? (int)$rowData[self::COL_PARENT_ID]
            : $this->storeManager->getStore()->getRootCategoryId();
        $category->setParentId($parentId);

        $this->setCategoryAttributes($category, $rowData);

        $this->categoryRepository->save($category);
        $this->countItemsCreated++;
    }

    protected function updateCategory($category, array $rowData): void
    {
        $storeId = !empty($rowData[self::COL_STORE_ID])
            ? (int)$rowData[self::COL_STORE_ID]
            : \Magento\Store\Model\Store::DEFAULT_STORE_ID;
        $category->setStoreId($storeId);

        if (isset($rowData[self::COL_NAME])) {
            $category->setName($rowData[self::COL_NAME]);
        }

        $this->setCategoryAttributes($category, $rowData);

        $this->categoryRepository->save($category);
        $this->countItemsUpdated++;
    }

    protected function setCategoryAttributes($category, array $rowData): void
    {
        if (isset($rowData[self::COL_POSITION])) {
            $category->setPosition((int)$rowData[self::COL_POSITION]);
        }

        if (isset($rowData[self::COL_IS_ACTIVE])) {
            $category->setIsActive((bool)$rowData[self::COL_IS_ACTIVE]);
        }

        if (!empty($rowData[self::COL_URL_KEY])) {
            $category->setUrlKey($rowData[self::COL_URL_KEY]);
        }

        if (isset($rowData[self::COL_DESCRIPTION])) {
            $category->setDescription($rowData[self::COL_DESCRIPTION]);
        }

        if (isset($rowData[self::COL_META_TITLE])) {
            $category->setMetaTitle($rowData[self::COL_META_TITLE]);
        }

        if (isset($rowData[self::COL_META_KEYWORDS])) {
            $category->setMetaKeywords($rowData[self::COL_META_KEYWORDS]);
        }

        if (isset($rowData[self::COL_META_DESCRIPTION])) {
            $category->setMetaDescription($rowData[self::COL_META_DESCRIPTION]);
        }

        if (isset($rowData[self::COL_INCLUDE_IN_MENU])) {
            $category->setIncludeInMenu((bool)$rowData[self::COL_INCLUDE_IN_MENU]);
        }

        if (!empty($rowData[self::COL_DISPLAY_MODE])) {
            $category->setDisplayMode($rowData[self::COL_DISPLAY_MODE]);
        }

        if (!empty($rowData[self::COL_AVAILABLE_SORT_BY])) {
            $availableSortBy = explode(',', $rowData[self::COL_AVAILABLE_SORT_BY]);
            $category->setAvailableSortBy($availableSortBy);
        }

        if (!empty($rowData[self::COL_DEFAULT_SORT_BY])) {
            $category->setDefaultSortBy($rowData[self::COL_DEFAULT_SORT_BY]);
        }
    }

    protected function findExistingCategory(array $rowData)
    {
        if (!empty($rowData[self::COL_ENTITY_ID])) {
            $cacheKey = 'id_' . $rowData[self::COL_ENTITY_ID];
            if (!isset($this->categoriesCache[$cacheKey])) {
                try {
                    $this->categoriesCache[$cacheKey] = $this->categoryRepository->get(
                        (int)$rowData[self::COL_ENTITY_ID]
                    );
                } catch (\Exception $e) {
                    return null;
                }
            }
            return $this->categoriesCache[$cacheKey];
        }

        if (!empty($rowData[self::COL_PATH])) {
            $cacheKey = 'path_' . $rowData[self::COL_PATH];
            if (!isset($this->categoriesCache[$cacheKey])) {
                $collection = $this->categoryCollectionFactory->create();
                $collection->addFieldToFilter('path', $rowData[self::COL_PATH]);
                $this->categoriesCache[$cacheKey] = $collection->getFirstItem();
            }
            $category = $this->categoriesCache[$cacheKey];
            return $category->getId() ? $category : null;
        }

        return null;
    }

    public function getValidColumnNames(): array
    {
        return $this->_validColumnNames;
    }
}
