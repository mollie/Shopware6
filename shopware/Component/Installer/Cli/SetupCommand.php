<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Installer\Cli;

use Mollie\Shopware\Component\Installer\PluginInstaller;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'mollie:configuration:setup',
    description: 'Installs and configures the plugin without the need to disable and activate it again.'
)]
final class SetupCommand extends Command
{
    public function __construct(
        private readonly PluginInstaller $pluginInstaller,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('MOLLIE Plugin Setup');

        try {
            $this->logger->info('Starting plugin setup from CLI command');

            $this->pluginInstaller->install(Context::createDefaultContext());

            $io->success('Plugin setup successfully finished. Data should now be existing as expected');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->logger->critical('Error when starting plugin setup on CLI: ' . $exception->getMessage());
            $io->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
