<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Payment\ExpressComponents;

use Mollie\Shopware\Component\Payment\ExpressComponents\DAL\SessionPaymentCollection;
use Mollie\Shopware\Component\Payment\ExpressComponents\ExpiredSessionPaymentTaskHandler;
use Mollie\Shopware\Component\Payment\ExpressComponents\SessionPaymentRepository;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Integration\Data\ShopwareTestBehaviour;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[CoversClass(ExpiredSessionPaymentTaskHandler::class)]
#[CoversClass(SessionPaymentRepository::class)]
#[Group('core')]
final class ExpiredSessionPaymentTaskHandlerTest extends TestCase
{
    use ShopwareTestBehaviour;
    use IntegrationTestBehaviour;

    private const UNPAID_EXPIRED = 'unpaid, 3 hours old';
    private const UNPAID_FRESH = 'unpaid, 1 hour old';
    private const PAID_EXPIRED = 'paid, 3 days old';
    private const PAID_FRESH = 'paid, 1 day old';

    /** @var array<string, string> */
    private array $entryIds = [];

    protected function setUp(): void
    {
        $this->entryIds = [
            self::UNPAID_EXPIRED => $this->createEntry('-3 hours', null),
            self::UNPAID_FRESH => $this->createEntry('-1 hour', null),
            self::PAID_EXPIRED => $this->createEntry('-3 days', Uuid::randomHex()),
            self::PAID_FRESH => $this->createEntry('-1 day', Uuid::randomHex()),
        ];
    }

    public function testExpiredSessionsAreDeletedFromTheDatabase(): void
    {
        $this->createHandler(expressComponentsEnabled: '1')->run();

        $this->assertSame([self::UNPAID_FRESH, self::PAID_FRESH], $this->getRemainingEntries());
    }

    #[DataProvider('disabledExpressComponentsProvider')]
    public function testNothingIsDeletedWhileExpressComponentsAreDisabled(?string $expressComponentsEnabled): void
    {
        $this->createHandler($expressComponentsEnabled)->run();

        $this->assertSame([self::UNPAID_EXPIRED, self::UNPAID_FRESH, self::PAID_EXPIRED, self::PAID_FRESH], $this->getRemainingEntries());
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function disabledExpressComponentsProvider(): array
    {
        return [
            'not configured' => [null],
            'switched off' => ['0'],
        ];
    }

    private function createHandler(?string $expressComponentsEnabled): ExpiredSessionPaymentTaskHandler
    {
        /** @var SystemConfigService $systemConfigService */
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        /** @var EntityRepository $scheduledTaskRepository */
        $scheduledTaskRepository = $this->getContainer()->get('scheduled_task.repository');
        /** @var EntityRepository $salesChannelRepository */
        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
        /** @var SessionPaymentRepository $sessionPaymentRepository */
        $sessionPaymentRepository = $this->getContainer()->get(SessionPaymentRepository::class);

        $settingsService = new SettingsService($systemConfigService, expressComponentsEnabled: $expressComponentsEnabled);

        return new ExpiredSessionPaymentTaskHandler(
            $scheduledTaskRepository,
            $sessionPaymentRepository,
            $salesChannelRepository,
            $settingsService,
            new NullLogger()
        );
    }

    private function createEntry(string $age, ?string $transactionId): string
    {
        $id = Uuid::randomHex();
        $createdAt = (new \DateTimeImmutable())->modify($age)->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->getSessionPaymentRepository()->create([[
            'id' => $id,
            'sessionId' => 'ses_' . $id,
            'transactionId' => $transactionId,
            'createdAt' => $createdAt,
        ]], Context::createDefaultContext());

        return $id;
    }

    /**
     * @return list<string> names of the entries of this test that are still stored
     */
    private function getRemainingEntries(): array
    {
        $entryIds = array_values($this->entryIds);
        $criteria = new Criteria($entryIds);
        $searchResult = $this->getSessionPaymentRepository()->searchIds($criteria, Context::createDefaultContext());

        /** @var array<string> $storedIds */
        $storedIds = $searchResult->getIds();

        $remaining = [];
        foreach ($this->entryIds as $name => $id) {
            if (! in_array($id, $storedIds, true)) {
                continue;
            }

            $remaining[] = $name;
        }

        return $remaining;
    }

    /**
     * @return EntityRepository<SessionPaymentCollection>
     */
    private function getSessionPaymentRepository(): EntityRepository
    {
        /** @var EntityRepository<SessionPaymentCollection> $repository */
        return $this->getContainer()->get('mollie_session_payments.repository');
    }
}
