<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Subscription\ScheduledTask;

use Exception;
use Kiener\MolliePayments\Service\ConfigService;
use Kiener\MolliePayments\Service\Subscription\EmailService;
use Kiener\MolliePayments\Service\LoggerService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class SendPrePaymentReminderEmailTaskHandler extends ScheduledTaskHandler
{
    /**
     * @var ConfigService
     */
    private $configService;

    /**
     * @var EmailService
     */
    private $emailService;

    /**
     * @var EntityRepositoryInterface
     */
    private $subscriptionToProductRepository;

    /**
     * @var LoggerService
     */
    protected $logger;

    /**
     * @param EntityRepositoryInterface $scheduledTaskRepository
     * @param EntityRepositoryInterface $subscriptionToProductRepository
     * @param ConfigService $configService
     * @param EmailService $emailService
     * @param LoggerService $logger
     */
    public function __construct(
        EntityRepositoryInterface $scheduledTaskRepository,
        EntityRepositoryInterface $subscriptionToProductRepository,
        ConfigService $configService,
        EmailService $emailService,
        LoggerService $logger
    ) {
        parent::__construct($scheduledTaskRepository);

        $this->subscriptionToProductRepository = $subscriptionToProductRepository;
        $this->configService = $configService;
        $this->emailService = $emailService;
        $this->logger = $logger;
    }

    /**
     * @return iterable
     */
    public static function getHandledMessages(): iterable
    {
        return [ SendPrePaymentReminderEmailTask::class ];
    }

    /**
     *  Send Prepayment Reminder Email
     * @throws Exception
     */
    public function run(): void
    {
        if ($this->configService->get(ConfigService::PRE_PAYMENT_REMINDER_EMAIL)) {
            $interval = new \DateInterval('P' . $this->configService->get(ConfigService::DAYS_BEFORE_REMINDER) . 'D');
            $prepaymentDate = (new \DateTimeImmutable)->sub($interval);

            $criteria = new Criteria();
            $criteria->addAssociation('salesChannels');
            $criteria->addAssociation('product');
            $criteria->addFilter(new EqualsFilter('nextPaymentDate', $prepaymentDate->format('Y-m-d')));

            $subscriptions = $this->subscriptionToProductRepository
                ->search($criteria, Context::createDefaultContext())
                ->getEntities();

            foreach ($subscriptions->getItems() as $subscription) {
                $result = $this->emailService->sendMail($subscription);

                if (!$result) {
                    $this->logger->addEntry(
                        date("Y-m-d H:i:s") . ': Prepayment reminder email was not sent. Subscription_id: ' . $subscription->getId(),
                        Context::createDefaultContext()
                    );
                }
            }
        }
    }
}
