<?php
declare(strict_types=1);

namespace Perspective\InventorySync\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Perspective\InventorySync\Helper\Data as ConfigHelper;
use Perspective\InventorySync\Model\InventorySyncClient;

class SyncAllCommand extends Command
{
    const OPTION_FORCE = 'force';
    const OPTION_LIMIT = 'limit';

    /**
     * @var CollectionFactory
     */
    private $productCollectionFactory;

    /**
     * @var StockRegistryInterface
     */
    private $stockRegistry;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var InventorySyncClient
     */
    private $syncClient;

    /**
     * @param CollectionFactory $productCollectionFactory
     * @param StockRegistryInterface $stockRegistry
     * @param ConfigHelper $configHelper
     * @param InventorySyncClient $syncClient
     * @param string|null $name
     */
    public function __construct(
        CollectionFactory $productCollectionFactory,
        StockRegistryInterface $stockRegistry,
        ConfigHelper $configHelper,
        InventorySyncClient $syncClient,
        ?string $name = null
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->stockRegistry = $stockRegistry;
        $this->configHelper = $configHelper;
        $this->syncClient = $syncClient;
        parent::__construct($name);
    }

    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName('perspective:inventory:sync-all')
            ->setDescription('Sync all products inventory to Laravel microservice')
            ->addOption(
                self::OPTION_FORCE,
                'f',
                InputOption::VALUE_NONE,
                'Force sync even if module is disabled'
            )
            ->addOption(
                self::OPTION_LIMIT,
                'l',
                InputOption::VALUE_OPTIONAL,
                'Limit number of products to sync',
                0
            );

        parent::configure();
    }

    /**
     * Execute command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $force = $input->getOption(self::OPTION_FORCE);
        $limit = (int) $input->getOption(self::OPTION_LIMIT);

        if (!$force && !$this->configHelper->isEnabled()) {
            $output->writeln('<error>Inventory sync is disabled. Use --force to sync anyway.</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Starting inventory sync to Laravel microservice...</info>');

        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect(['sku', 'name']);

        if ($limit > 0) {
            $collection->setPageSize($limit);
        }

        $total = $collection->count();
        $synced = 0;
        $failed = 0;

        $output->writeln(sprintf('<comment>Total products to sync: %d</comment>', $total));

        foreach ($collection as $product) {
            try {
                $sku = $product->getSku();
                $name = $product->getName();
                $stockItem = $this->stockRegistry->getStockItemBySku($sku);
                $quantity = (int) $stockItem->getQty();

                $result = $this->syncClient->syncInventory(
                    $sku,
                    $quantity,
                    $name,
                    (int) $product->getId()
                );

                if ($result) {
                    $synced++;
                    $output->writeln(sprintf(
                        '<info>✓ Synced: %s - %s (Qty: %d)</info>',
                        $sku,
                        $name,
                        $quantity
                    ));
                } else {
                    $failed++;
                    $output->writeln(sprintf(
                        '<error>✗ Failed: %s - %s</error>',
                        $sku,
                        $name
                    ));
                }
            } catch (\Exception $e) {
                $failed++;
                $output->writeln(sprintf(
                    '<error>✗ Error syncing %s: %s</error>',
                    $product->getSku(),
                    $e->getMessage()
                ));
            }
        }

        $output->writeln('');
        $output->writeln('<info>Sync completed!</info>');
        $output->writeln(sprintf('<comment>Successfully synced: %d</comment>', $synced));
        $output->writeln(sprintf('<comment>Failed: %d</comment>', $failed));

        return Command::SUCCESS;
    }
}
