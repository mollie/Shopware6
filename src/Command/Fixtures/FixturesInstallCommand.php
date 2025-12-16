<?php

declare(strict_types=1);

namespace Kiener\MolliePayments\Command\Fixtures;

use Kiener\MolliePayments\Components\Fixtures\FixturesInstaller;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

class FixturesInstallCommand extends Command
{
    /** @var string */
    public static $defaultName = 'mollie:fixtures:install';

    private string $appEnv;

    private FixturesInstaller $fixturesInstaller;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(string $appEnv, FixturesInstaller $fixturesInstaller, LoggerInterface $logger)
    {
        $this->appEnv = $appEnv;
        $this->fixturesInstaller = $fixturesInstaller;
        $this->logger = $logger;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName(self::$defaultName)
            ->setDescription('Sets up the Mollie Payments demo data fixtures.')
            ->addOption('setup', null, null, 'Only prepares payment methods for your sales channels. No demo data is installed with this option.')
            ->addOption('data', null, null, 'Only install sample demo data like products and categories to easily test features.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Skip environment safety question')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Mollie Payments - Setting up Fixtures');

        try {
            $modeSetup = $input->getOption('setup');
            $modeDemoData = $input->getOption('data');
            $force = (bool) $input->getOption('force');

            if ($modeSetup) {
                $io->note('Mode: SETUP');
            } elseif ($modeDemoData) {
                $io->note('Mode: DEMO DATA');
            } else {
                $io->note('Mode: ALL');
            }

            if (strtolower($this->appEnv) !== 'dev' && ! $force) {
                /** @var QuestionHelper $helper */
                $helper = $this->getHelper('question');
                $question = new ConfirmationQuestion('APP_ENV is ' . $this->appEnv . '. Continue anyway? (y/n) ', false);

                if (! $helper->ask($input, $output, $question)) {
                    $io->writeln('Aborting.');

                    return Command::FAILURE;
                }
                $io->writeln('Continuing with fixture setup...');
            }

            $this->logger->info('Installing Mollie Payments fixtures via CLI command.',
                [
                    'setupMode' => $modeSetup,
                    'demoDataMode' => $modeDemoData
                ]
            );

            $this->fixturesInstaller->install($modeSetup, $modeDemoData);

            $io->success('Mollie Payments fixtures have been set up successfully.');

            $this->logger->info('Mollie Payments fixtures installation completed successfully.');

            return Command::SUCCESS;
        } catch (\Throwable $ex) {
            $this->logger->error($ex->getMessage(), ['exception' => $ex]);

            return Command::FAILURE;
        }
    }
}
