<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Cli;

use Mollie\Shopware\Component\Subscription\SubscriptionRenewalReminder;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'mollie:subscriptions:renewal-reminder',
    description: 'Processes Subscription renewal reminders of upcoming renewals'
)]
final class RenewalReminderCommand extends Command
{
    public function __construct(
        private readonly SubscriptionRenewalReminder $renewalReminder,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('MOLLIE Subscription Renewal Reminders');

        try {
            $this->logger->info('Starting Subscription Renewal Reminder on CLI');
            $count = $this->renewalReminder->remind(Context::createDefaultContext());
            $io->success(sprintf('%d subscription renewal reminders processed', $count));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->logger->critical('Subscription renewal reminder CLI command failed: ' . $exception->getMessage());
            $io->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
