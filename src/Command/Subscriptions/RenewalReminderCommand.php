<?php

namespace Kiener\MolliePayments\Command\Subscriptions;

use Kiener\MolliePayments\Components\Subscription\SubscriptionManager;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RenewalReminderCommand extends Command
{
    public static $defaultName = 'mollie:subscriptions:renewal-reminder';


    /**
     * @var SubscriptionManager
     */
    private $subscriptionManager;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param SubscriptionManager $subscriptionManager
     * @param LoggerInterface $logger
     */
    public function __construct(SubscriptionManager $subscriptionManager, LoggerInterface $logger)
    {
        $this->subscriptionManager = $subscriptionManager;
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
            ->setDescription('Processes Subscription renewal reminders of upcoming renewals');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('MOLLIE Subscription Renewal Reminders');

        try {
            $this->logger->info('Starting Subscription Renewal Reminder on CLI');

            $context = Context::createDefaultContext();

            $remindedCount = $this->subscriptionManager->remindSubscriptionRenewal($context);

            $this->logger->debug($remindedCount . ' subscriptions renewal reminders have been processed successfully!');

            $io->success($remindedCount . ' subscriptions renewal reminders have been processed successfully!');

            return 0;
        } catch (\Throwable $exception) {
            $this->logger->critical('Error when processing subscription renewal reminders on CLI: ' . $exception->getMessage());

            $io->error($exception->getMessage());

            return 1;
        }
    }
}
