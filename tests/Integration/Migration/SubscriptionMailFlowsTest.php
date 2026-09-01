<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Migration;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Kiener\MolliePayments\Migration\Migration1777881160RenewalReminderMailTemplate;
use Kiener\MolliePayments\Migration\Migration1778100100SubscriptionPriceChangeMailTemplate;
use Kiener\MolliePayments\Migration\Migration1782345600SubscriptionMailFlows;
use Mollie\Shopware\Integration\Data\ShopwareTestBehaviour;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

#[CoversClass(Migration1782345600SubscriptionMailFlows::class)]
#[Group('core')]
final class SubscriptionMailFlowsTest extends TestCase
{
    use ShopwareTestBehaviour;
    use IntegrationTestBehaviour;

    #[DataProvider('eventNameProvider')]
    public function testFlowWithMailSendSequenceExistsAfterMigration(string $eventName): void
    {
        $connection = $this->getConnection();
        $this->runMigrations($connection);

        $flowId = $connection->fetchOne(
            'SELECT `id` FROM `flow` WHERE `event_name` = :eventName LIMIT 1',
            ['eventName' => $eventName]
        );
        $this->assertIsString($flowId);
        $this->assertNotSame('', $flowId);

        $config = $connection->fetchOne(
            'SELECT `config` FROM `flow_sequence` WHERE `flow_id` = :flowId AND `action_name` = :action LIMIT 1',
            ['flowId' => $flowId, 'action' => 'action.mail.send']
        );
        $this->assertIsString($config);

        $decoded = json_decode($config, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('mailTemplateId', $decoded);
        $this->assertNotEmpty($decoded['mailTemplateId']);
        $this->assertSame('default', $decoded['recipient']['type']);
    }

    #[DataProvider('eventNameProvider')]
    public function testFlowTemplateExistsAfterMigration(string $eventName): void
    {
        $connection = $this->getConnection();
        $this->runMigrations($connection);

        $count = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM `flow_template` WHERE JSON_UNQUOTE(JSON_EXTRACT(`config`, \'$.eventName\')) = :eventName',
            ['eventName' => $eventName]
        );
        $this->assertSame(1, $count);
    }

    #[DataProvider('eventNameProvider')]
    public function testMigrationIsIdempotent(string $eventName): void
    {
        $connection = $this->getConnection();
        $this->runMigrations($connection);

        // Second run must not create duplicate flow / flow_template for the same event.
        (new Migration1782345600SubscriptionMailFlows())->update($connection);

        $flowCount = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM `flow` WHERE `event_name` = :eventName',
            ['eventName' => $eventName]
        );
        $this->assertSame(1, $flowCount);

        $templateCount = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM `flow_template` WHERE JSON_UNQUOTE(JSON_EXTRACT(`config`, \'$.eventName\')) = :eventName',
            ['eventName' => $eventName]
        );
        $this->assertSame(1, $templateCount);
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function eventNameProvider(): array
    {
        return [
            'renewal_reminder' => ['mollie.subscription.renewal_reminder'],
            'priceChangeNotice' => ['mollie.subscription.priceChangeNotice'],
        ];
    }

    private function runMigrations(Connection $connection): void
    {
        $this->removeExistingFlows($connection);

        // The flow migration links the mail templates, so they must exist first.
        (new Migration1777881160RenewalReminderMailTemplate())->update($connection);
        (new Migration1778100100SubscriptionPriceChangeMailTemplate())->update($connection);
        (new Migration1782345600SubscriptionMailFlows())->update($connection);
    }

    private function removeExistingFlows(Connection $connection): void
    {
        $events = array_map(function (array $row): string {
            return $row[0];
        }, array_values(self::eventNameProvider()));

        $connection->executeStatement(
            'DELETE FROM `flow` WHERE `event_name` IN (:events)',
            ['events' => $events],
            ['events' => ArrayParameterType::STRING]
        );

        $connection->executeStatement(
            'DELETE FROM `flow_template` WHERE JSON_UNQUOTE(JSON_EXTRACT(`config`, \'$.eventName\')) IN (:events)',
            ['events' => $events],
            ['events' => ArrayParameterType::STRING]
        );
    }

    private function getConnection(): Connection
    {
        /** @var Connection $connection */
        return $this->getContainer()->get(Connection::class);
    }
}
