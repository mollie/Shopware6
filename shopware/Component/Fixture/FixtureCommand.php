<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Fixture;

use Composer\Console\Input\InputArgument;
use Composer\Console\Input\InputOption;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When('dev')]
#[AsCommand('mollie:fixtures:load', 'Install or remove mollie fixtures')]
final class FixtureCommand extends Command
{

    public function __construct(
        #[AutowireIterator('mollie.fixture')]
        private iterable $fixtures
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('uninstall', 'r', InputOption::VALUE_NONE, 'uninstall all fixtures');
        $this->addArgument('group', InputArgument::IS_ARRAY, 'Groups to install', []);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $groupArguments = $input->getArgument('group');
        $groups = [];
        if (count($groupArguments) > 0) {
            foreach ($groupArguments as $group) {
                $groups[] = FixtureGroup::from($group);
            }
        }
        $fixtures = iterator_to_array($this->fixtures);

        $context = new Context(new SystemSource());
        $isUninstall = $input->getOption('uninstall');

        if ($isUninstall) {
            $output->writeln('<info>uninstalling all fixtures</info>');

            uasort($fixtures,function (AbstractFixture $a, AbstractFixture $b){
                return $a->getPriority() <=> $b->getPriority();
            });

            foreach ($fixtures as $fixture) {
                $fixture->uninstall($context);
            }
            $output->writeln('<info>finished uninstalling</info>');
            return Command::SUCCESS;
        }
        $output->writeln('<info>installing fixtures</info>');


        uasort($fixtures,function (AbstractFixture $a, AbstractFixture $b){
            return $b->getPriority() <=> $a->getPriority();
        });

        /** @var AbstractFixture $fixture */
        foreach ($fixtures as $fixture) {
            if (count($groups) > 0 && !in_array($fixture->getGroup(), $groups)) {
                continue;
            }
            $fixtureClass = (string)get_class($fixture);
            $fixtureGroup = $fixture->getGroup()->value;
            $output->writeln('<info>installing ' . $fixtureClass . ' group:' . $fixtureGroup . '</info>');
            $fixture->install($context);
        }
        $output->writeln('<info>finished installing fixtures</info>');
        return Command::SUCCESS;
    }

}
