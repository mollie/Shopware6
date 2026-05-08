<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Migration;

use Doctrine\DBAL\Connection;
use Kiener\MolliePayments\Migration\Migration1778100100SubscriptionPriceChangeMailTemplate;
use Mollie\Shopware\Integration\Data\ShopwareTestBehaviour;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

#[CoversClass(Migration1778100100SubscriptionPriceChangeMailTemplate::class)]
final class SubscriptionPriceChangeMailTemplateTest extends TestCase
{
    use ShopwareTestBehaviour;
    use IntegrationTestBehaviour;

    private const TECHNICAL_NAME = 'mollie_subscription_price_change';

    public function testMailTemplateTypeExistsAfterMigration(): void
    {
        $connection = $this->getConnection();

        (new Migration1778100100SubscriptionPriceChangeMailTemplate())->update($connection);

        $typeId = $connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :name LIMIT 1',
            ['name' => self::TECHNICAL_NAME]
        );

        $this->assertIsString($typeId);
        $this->assertNotSame('', $typeId);
    }

    public function testMailTemplateRowIsLinkedToType(): void
    {
        $connection = $this->getConnection();

        (new Migration1778100100SubscriptionPriceChangeMailTemplate())->update($connection);

        $typeId = $connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :name LIMIT 1',
            ['name' => self::TECHNICAL_NAME]
        );

        $this->assertIsString($typeId);

        $templateId = $connection->fetchOne(
            'SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :typeId LIMIT 1',
            ['typeId' => $typeId]
        );

        $this->assertIsString($templateId);
        $this->assertNotSame('', $templateId);
    }

    public function testMigrationIsIdempotent(): void
    {
        $connection = $this->getConnection();

        $migration = new Migration1778100100SubscriptionPriceChangeMailTemplate();
        $migration->update($connection);
        $migration->update($connection);

        $typeCount = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM `mail_template_type` WHERE `technical_name` = :name',
            ['name' => self::TECHNICAL_NAME]
        );
        $this->assertSame(1, $typeCount);

        $typeId = $connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :name LIMIT 1',
            ['name' => self::TECHNICAL_NAME]
        );

        $templateCount = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM `mail_template` WHERE `mail_template_type_id` = :typeId',
            ['typeId' => $typeId]
        );
        $this->assertSame(1, $templateCount);
    }

    public function testTemplateTranslationsContainExpectedSubjects(): void
    {
        $connection = $this->getConnection();

        (new Migration1778100100SubscriptionPriceChangeMailTemplate())->update($connection);

        $typeId = $connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :name LIMIT 1',
            ['name' => self::TECHNICAL_NAME]
        );

        $templateId = $connection->fetchOne(
            'SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :typeId LIMIT 1',
            ['typeId' => $typeId]
        );

        $subjects = $connection->fetchFirstColumn(
            'SELECT `subject` FROM `mail_template_translation` WHERE `mail_template_id` = :templateId',
            ['templateId' => $templateId]
        );

        $this->assertNotEmpty($subjects);
        foreach ($subjects as $subject) {
            $this->assertStringContainsString('{{ salesChannel.name }}', (string) $subject);
        }
    }

    private function getConnection(): Connection
    {
        /** @var Connection $connection */
        return $this->getContainer()->get(Connection::class);
    }
}
