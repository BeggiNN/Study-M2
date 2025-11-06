<?php
declare(strict_types=1);

namespace Perspective\CheckoutField\Console\Command;

use Perspective\CheckoutField\Model\Soap\VatCheckClientFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TestSoapVat extends Command
{
    private const ARGUMENT_COUNTRY = 'country';
    private const ARGUMENT_VAT = 'vat';
    private const OPTION_BASE_URL = 'base-url';

    /**
     * @param VatCheckClientFactory $clientFactory
     * @param string|null $name
     */
    public function __construct(
        private readonly VatCheckClientFactory $clientFactory,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('perspective:vat:test-soap')
            ->setDescription('Test VAT validation via SOAP API')
            ->addArgument(
                self::ARGUMENT_COUNTRY,
                InputArgument::REQUIRED,
                'Country code (e.g., PL, DE, FR)'
            )
            ->addArgument(
                self::ARGUMENT_VAT,
                InputArgument::REQUIRED,
                'VAT number'
            )
            ->addOption(
                self::OPTION_BASE_URL,
                'u',
                InputOption::VALUE_OPTIONAL,
                'Base URL (default: current store base URL)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $countryCode = $input->getArgument(self::ARGUMENT_COUNTRY);
        $vatNumber = $input->getArgument(self::ARGUMENT_VAT);
        $baseUrl = $input->getOption(self::OPTION_BASE_URL);

        $output->writeln('<info>Testing SOAP VAT validation...</info>');
        $output->writeln(sprintf('Country: %s', $countryCode));
        $output->writeln(sprintf('VAT Number: %s', $vatNumber));

        if ($baseUrl) {
            $output->writeln(sprintf('Base URL: %s', $baseUrl));
        }

        $output->writeln('');

        try {
            $client = $this->clientFactory->create($baseUrl);

            $result = $client->validateVatNumber($countryCode, $vatNumber);

            if ($result['success']) {
                $status = $result['valid'] ?? false ? '<info>✓ Valid</info>' : '<error>✗ Invalid</error>';
                $output->writeln(sprintf('Result: %s', $status));

                if (isset($result['message'])) {
                    $output->writeln(sprintf('Message: %s', $result['message']));
                }

                return Command::SUCCESS;
            }

            $output->writeln('<error>Validation failed</error>');
            if (isset($result['message'])) {
                $output->writeln(sprintf('Error: %s', $result['message']));
            }

            return Command::FAILURE;

        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
