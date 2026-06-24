<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Logger;

use Mollie\Shopware\Component\Logger\CleanUpLoggerScheduledTaskHandler;
use Mollie\Shopware\Component\Logger\OrderLogStorage;
use Mollie\Shopware\Component\Settings\Struct\LoggerSettings;
use Mollie\Shopware\Unit\Fake\FakeOrderSearchRepository;
use Mollie\Shopware\Unit\Fake\FakeScheduledTaskRepository;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

#[CoversClass(CleanUpLoggerScheduledTaskHandler::class)]
final class CleanUpLoggerScheduledTaskHandlerTest extends TestCase
{
    private const SUCCESS_DAYS = 7;
    private const FAILED_DAYS = 30;

    private string $logDir;

    private FakeOrderSearchRepository $orderRepository;

    protected function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/mollie-logger-test-' . uniqid('', true);
        mkdir($this->logDir . '/mollie', 0777, true);
        $this->orderRepository = new FakeOrderSearchRepository();
    }

    protected function tearDown(): void
    {
        $mollieDir = $this->logDir . '/mollie';
        foreach (glob($mollieDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($mollieDir)) {
            rmdir($mollieDir);
        }
        if (is_dir($this->logDir)) {
            rmdir($this->logDir);
        }
    }

    public function testSuccessfulOrderLogIsDeletedAfterSuccessRetention(): void
    {
        $this->addOrder('10000', 'paid');
        $logFile = $this->createLogFile('order-10000.log', 10);

        $this->createHandler()->run();

        $this->assertFileDoesNotExist($logFile);
    }

    public function testSuccessfulOrderLogIsKeptWithinSuccessRetention(): void
    {
        $this->addOrder('10000', 'paid');
        $logFile = $this->createLogFile('order-10000.log', 3);

        $this->createHandler()->run();

        $this->assertFileExists($logFile);
    }

    public function testFailedOrderLogIsKeptWithinFailedRetention(): void
    {
        $this->addOrder('20000', 'failed');
        $logFile = $this->createLogFile('order-20000.log', 10);

        $this->createHandler()->run();

        $this->assertFileExists($logFile);
    }

    public function testFailedOrderLogIsDeletedAfterFailedRetention(): void
    {
        $this->addOrder('20000', 'cancelled');
        $logFile = $this->createLogFile('order-20000.log', 40);

        $this->createHandler()->run();

        $this->assertFileDoesNotExist($logFile);
    }

    public function testUnknownOrderUsesFailedRetention(): void
    {
        // no order added to the repository -> treated as not successful
        $keptFile = $this->createLogFile('order-30000.log', 10);
        $deletedFile = $this->createLogFile('order-40000.log', 40);

        $this->createHandler()->run();

        $this->assertFileExists($keptFile);
        $this->assertFileDoesNotExist($deletedFile);
    }

    public function testAuthorizedOrderIsTreatedAsSuccessful(): void
    {
        $this->addOrder('50000', 'authorized');
        $logFile = $this->createLogFile('order-50000.log', 10);

        $this->createHandler()->run();

        $this->assertFileDoesNotExist($logFile);
    }

    public function testNonOrderFilesAreIgnored(): void
    {
        $other = $this->createLogFile('something-else.log', 40);

        $this->createHandler()->run();

        $this->assertFileExists($other);
    }

    private function createHandler(): CleanUpLoggerScheduledTaskHandler
    {
        $loggerSettings = new LoggerSettings(false, 0, self::SUCCESS_DAYS, self::FAILED_DAYS);
        $settingsService = new FakeSettingsService($loggerSettings);

        return new CleanUpLoggerScheduledTaskHandler(
            new FakeScheduledTaskRepository(),
            new OrderLogStorage($this->logDir),
            $settingsService,
            $this->orderRepository,
            new NullLogger()
        );
    }

    private function addOrder(string $orderNumber, string $transactionState): void
    {
        $state = new StateMachineStateEntity();
        $state->setId('state-' . $transactionState);
        $state->setTechnicalName($transactionState);

        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction-' . $orderNumber);
        $transaction->setStateMachineState($state);

        $order = new OrderEntity();
        $order->setId('order-' . $orderNumber);
        $order->setOrderNumber($orderNumber);
        $order->setTransactions(new OrderTransactionCollection([$transaction]));

        $this->orderRepository->add($order);
    }

    private function createLogFile(string $name, int $ageInDays): string
    {
        $path = $this->logDir . '/mollie/' . $name;
        file_put_contents($path, 'log content');
        touch($path, time() - ($ageInDays * 24 * 60 * 60));

        return $path;
    }
}
