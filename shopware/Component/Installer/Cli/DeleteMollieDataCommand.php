<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Installer\Cli;

use Mollie\Shopware\Component\Installer\MollieDataRemover;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'mollie:delete-data',
    description: 'Removes all Mollie-owned data: system config, custom field definitions and customer/product custom field values, and unused payment methods. Payment methods still used by orders are only deactivated. Subscriptions, refunds and order data are kept. Create a database backup first.'
)]
final class DeleteMollieDataCommand extends Command
{
    public function __construct(
        private readonly MollieDataRemover $dataRemover,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip the confirmation prompt');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Mollie Data Removal');
        $io->warning('This permanently removes Mollie config, custom fields and unused payment methods. Make sure you have a database backup.');

        $force = (bool) $input->getOption('force');
        if ($force === false && $io->confirm('Do you want to continue?', false) === false) {
            $io->note('Aborted, no data was removed.');

            return self::SUCCESS;
        }

        try {
            $this->dataRemover->removeAllData(Context::createDefaultContext());

            $io->success('All removable Mollie data has been removed.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->logger->critical('Error while removing Mollie data: ' . $exception->getMessage());
            $io->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
