<?php
declare(strict_types=1);

namespace Perspective\InventorySync\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Perspective\InventorySync\Helper\Data as ConfigHelper;
use Perspective\InventorySync\Model\InventorySyncClient;

class SyncProductCommand extends Command
{
    const ARGUMENT_SKU = 'sku';

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

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
     * @param ProductRepositoryInterface $productRepository
     * @param StockRegistryInterface $stockRegistry
     * @param ConfigHelper $configHelper
     * @param InventorySyncClient $syncClient
     * @param string|null $name
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        StockRegistryInterface $stockRegistry,
        ConfigHelper $configHelper,
        InventorySyncClient $syncClient,
        ?string $name = null
    ) {
        $this->productRepository = $productRepository;
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
        $this->setName('perspective:inventory:sync-product')
            ->setDescription('Sync specific product inventory to Laravel microservice')
            ->addArgument(
                self::ARGUMENT_SKU,
                InputArgument::REQUIRED,
                'Product SKU to sync'
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
        if (!$this->configHelper->isEnabled()) {
            $output->writeln('<error>Inventory sync is disabled in configuration.</error>');
            return Command::FAILURE;
        }

        $sku = $input->getArgument(self::ARGUMENT_SKU);

        $output->writeln(sprintf('<info>Syncing product: %s</info>', $sku));

        try {
            $product = $this->productRepository->get($sku);
            $stockItem = $this->stockRegistry->getStockItemBySku($sku);
            $quantity = (int) $stockItem->getQty();
            $name = $product->getName();

            $output->writeln(sprintf('<comment>Product ID: %d</comment>', $product->getId()));
            $output->writeln(sprintf('<comment>Product Name: %s</comment>', $name));
            $output->writeln(sprintf('<comment>Current quantity: %d</comment>', $quantity));

            $result = $this->syncClient->syncInventory(
                $sku,
                $quantity,
                $name,
                (int) $product->getId()
            );

            if ($result) {
                $output->writeln('<info>✓ Product successfully synced to Laravel microservice!</info>');
                return Command::SUCCESS;
            } else {
                $output->writeln('<error>✗ Failed to sync product. Check logs for details.</error>');
                return Command::FAILURE;
            }
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $output->writeln(sprintf('<error>Product with SKU "%s" not found.</error>', $sku));
            return Command::FAILURE;
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
