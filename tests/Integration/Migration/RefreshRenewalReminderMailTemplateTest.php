<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Migration;

use Doctrine\DBAL\Connection;
use Kiener\MolliePayments\Migration\Migration1777881160RenewalReminderMailTemplate;
use Kiener\MolliePayments\Migration\Migration1782889200RefreshRenewalReminderMailTemplate;
use Mollie\Shopware\Integration\Data\ShopwareTestBehaviour;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(Migration1782889200RefreshRenewalReminderMailTemplate::class)]
final class RefreshRenewalReminderMailTemplateTest extends TestCase
{
    use ShopwareTestBehaviour;
    use IntegrationTestBehaviour;

    private const TECHNICAL_NAME = 'mollie_subscriptions_renewal_reminder';

    private const STALE_HTML = 'Total: {{ subscription.amount }} {{ subscription.currency }}';
    private const STALE_PLAIN = 'Total: {{ subscription.amount }} {{ subscription.currency }}';

    public function testRefreshesStaleTemplate(): void
    {
        $connection = $this->getConnection();
        (new Migration1777881160RenewalReminderMailTemplate())->update($connection);

        $languageId = $this->makeTemplateStale($connection);

        (new Migration1782889200RefreshRenewalReminderMailTemplate())->update($connection);

        $content = $this->fetchContentHtml($connection, $languageId);
        $this->assertStringContainsString('subscription.currency.symbol', $content);
        $this->assertStringNotContainsString('{{ subscription.currency }}', $content);
    }

    public function testIsIdempotent(): void
    {
        $connection = $this->getConnection();
        (new Migration1777881160RenewalReminderMailTemplate())->update($connection);
        $languageId = $this->makeTemplateStale($connection);

        (new Migration1782889200RefreshRenewalReminderMailTemplate())->update($connection);
        $first = $this->fetchContentHtml($connection, $languageId);

        (new Migration1782889200RefreshRenewalReminderMailTemplate())->update($connection);
        $second = $this->fetchContentHtml($connection, $languageId);

        $this->assertSame($first, $second);
    }

    public function testLeavesCustomizedTemplateUntouched(): void
    {
        $connection = $this->getConnection();
        (new Migration1777881160RenewalReminderMailTemplate())->update($connection);

        $custom = 'My custom text {{ subscription.currency.symbol ?? \'\' }}';
        $languageId = $this->setContent($connection, $custom, $custom);

        (new Migration1782889200RefreshRenewalReminderMailTemplate())->update($connection);

        $this->assertSame($custom, $this->fetchContentHtml($connection, $languageId));
    }

    private function makeTemplateStale(Connection $connection): string
    {
        return $this->setContent($connection, self::STALE_HTML, self::STALE_PLAIN);
    }

    private function setContent(Connection $connection, string $html, string $plain): string
    {
        $templateId = $this->fetchTemplateId($connection);
        $languageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $connection->executeStatement(
            'UPDATE `mail_template_translation`
                SET `content_html` = :html, `content_plain` = :plain
                WHERE `mail_template_id` = :templateId AND `language_id` = :languageId',
            [
                'html' => $html,
                'plain' => $plain,
                'templateId' => $templateId,
                'languageId' => $languageId,
            ]
        );

        return $languageId;
    }

    private function fetchTemplateId(Connection $connection): string
    {
        $typeId = $connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :name LIMIT 1',
            ['name' => self::TECHNICAL_NAME]
        );

        $templateId = $connection->fetchOne(
            'SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :typeId LIMIT 1',
            ['typeId' => $typeId]
        );
        $this->assertIsString($templateId);

        return $templateId;
    }

    private function fetchContentHtml(Connection $connection, string $languageId): string
    {
        $templateId = $this->fetchTemplateId($connection);

        $content = $connection->fetchOne(
            'SELECT `content_html` FROM `mail_template_translation`
                WHERE `mail_template_id` = :templateId AND `language_id` = :languageId',
            ['templateId' => $templateId, 'languageId' => $languageId]
        );
        $this->assertIsString($content);

        return $content;
    }

    private function getConnection(): Connection
    {
        /** @var Connection $connection */
        return $this->getContainer()->get(Connection::class);
    }
}
