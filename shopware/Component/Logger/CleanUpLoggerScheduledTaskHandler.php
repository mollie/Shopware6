<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Logger;

use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: CleanUpLoggerScheduledTask::class)]
final class CleanUpLoggerScheduledTaskHandler extends ScheduledTaskHandler
{
    private const MAX_DELETE_PER_RUN = 100;

    /**
     * @param EntityRepository<ScheduledTaskCollection<ScheduledTaskEntity>> $scheduledTaskRepository
     */
    public function __construct(
        #[Autowire(service: 'scheduled_task.repository')]
        EntityRepository $scheduledTaskRepository,
        #[Autowire(value: '%kernel.logs_dir%')]
        private string $logDir,
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $loggerSettings = $this->settingsService->getLoggerSettings();
        $logFileDays = $loggerSettings->getLogFileDays();
        $mollieLogDir = $this->logDir . '/mollie';
        if (! is_dir($mollieLogDir)) {
            return;
        }
        try {
            $daysToKeep = $logFileDays;
            $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);

            $deletedCount = 0;
            $handle = opendir($mollieLogDir);

            if ($handle === false) {
                $this->logger->warning('Could not open mollie log directory', ['dir' => $mollieLogDir]);

                return;
            }

            while (($file = readdir($handle)) !== false) {
                if ($deletedCount >= self::MAX_DELETE_PER_RUN) {
                    break;
                }

                if (! str_starts_with($file, 'order-') || ! str_ends_with($file, '.log')) {
                    continue;
                }

                $logFile = $mollieLogDir . '/' . $file;

                if (! is_file($logFile)) {
                    continue;
                }

                if (filemtime($logFile) < $cutoffTime) {
                    try {
                        unlink($logFile);
                        ++$deletedCount;
                    } catch (\Throwable $e) {
                        $this->logger->warning('Could not delete log file: ' . $file, [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            closedir($handle);

            $this->logger->debug('Cleanup logger task executed', [
                'filesDeleted' => $deletedCount,
                'daysToKeep' => $daysToKeep,
                'maxPerRun' => self::MAX_DELETE_PER_RUN,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Error in cleanup logger task: ' . $e->getMessage());
        }
    }

    /**
     * @return iterable<mixed>
     */
    public static function getHandledMessages(): iterable
    {
        return [
            CleanUpLoggerScheduledTask::class,
        ];
    }
}
