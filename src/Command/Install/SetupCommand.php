<?php

namespace Kiener\MolliePayments\Command\Install;

use Kiener\MolliePayments\Components\Installer\PluginInstaller;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SetupCommand extends Command
{
    public static $defaultName = 'mollie:configuration:setup';


    /**
     * @var PluginInstaller
     */
    private $pluginInstaller;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param PluginInstaller $pluginInstaller
     * @param LoggerInterface $logger
     */
    public function __construct(PluginInstaller $pluginInstaller, LoggerInterface $logger)
    {
        $this->pluginInstaller = $pluginInstaller;
        $this->logger = $logger;

        parent::__construct();
    }


    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName((string)self::$defaultName)
            ->setDescription('Installs and configures the plugin without the need to disable and activate it again.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('MOLLIE Plugin Setup');

        try {
            $this->logger->info('Starting plugin setup from CLI command');

            $context = Context::createDefaultContext();
            $this->pluginInstaller->install($context);

            $io->success('Plugin setup successfully finished. Data should now be existing as expected');

            return 0;
        } catch (\Throwable $exception) {
            $this->logger->critical('Error when starting plugin setup on CLI: ' . $exception->getMessage());

            $io->error($exception->getMessage());

            return 1;
        }
    }
}
