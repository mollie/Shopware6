<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Refreshes the stale renewal reminder mail template.
 *
 * The template type "mollie_subscriptions_renewal_reminder" already exists on
 * stores upgraded from a pre-refactor plugin version, so
 * Migration1777881160RenewalReminderMailTemplate skips it (INSERT IGNORE +
 * early-return) and never updates its content. Back then `subscription.currency`
 * was a plain string field and the template rendered `{{ subscription.currency }}`.
 * After the Payments-API refactor `currency` is a ManyToOne association to
 * CurrencyEntity, so that token no longer renders and the reminder mail breaks.
 *
 * This migration rewrites the content to the current file version, but only for
 * translations still carrying the old string-era token, so already-corrected or
 * merchant-customized templates that use the new `{{ subscription.currency.symbol }}`
 * syntax are left untouched. Re-running is a no-op once the token is gone.
 *
 * @internal
 */
final class Migration1782889200RefreshRenewalReminderMailTemplate extends MigrationStep
{
    private const TECHNICAL_NAME = 'mollie_subscriptions_renewal_reminder';

    private const TEMPLATE_DIR = __DIR__ . '/../Resources/views/mail/RenewalReminder';

    /**
     * The string-era token that identifies a stale template translation.
     */
    private const STALE_TOKEN = '%subscription.currency }}%';

    public function getCreationTimestamp(): int
    {
        return 1782889200;
    }

    public function update(Connection $connection): void
    {
        $typeId = $connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :name LIMIT 1',
            ['name' => self::TECHNICAL_NAME]
        );
        if (! is_string($typeId) || $typeId === '') {
            return;
        }

        // The pre-refactor type registered `subscription` as a plain string, so
        // the admin preview cannot resolve the currency association. Refresh it.
        $connection->executeStatement(
            'UPDATE `mail_template_type` SET `available_entities` = :entities WHERE `id` = :id',
            [
                'entities' => json_encode([
                    'customer' => 'customer',
                    'subscription' => 'mollie_subscription',
                    'salesChannel' => 'sales_channel',
                ], JSON_THROW_ON_ERROR),
                'id' => $typeId,
            ]
        );

        $templateIds = $connection->fetchFirstColumn(
            'SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :typeId',
            ['typeId' => $typeId]
        );
        if ($templateIds === []) {
            return;
        }

        $enLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deLangId = $this->getLanguageIdByLocale($connection, 'de-DE');
        $systemLangId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        foreach ($templateIds as $templateId) {
            if ($enLangId !== null) {
                $this->refreshTranslation($connection, $templateId, $enLangId, 'en.html', 'en.txt');
            }
            if ($deLangId !== null) {
                $this->refreshTranslation($connection, $templateId, $deLangId, 'de.html', 'de.txt');
            }
            if ($systemLangId !== $enLangId && $systemLangId !== $deLangId) {
                $this->refreshTranslation($connection, $templateId, $systemLangId, 'en.html', 'en.txt');
            }
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function refreshTranslation(Connection $connection, string $templateId, string $languageId, string $htmlFile, string $txtFile): void
    {
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $connection->executeStatement(
            'UPDATE `mail_template_translation`
                SET `content_html` = :html, `content_plain` = :plain, `updated_at` = :now
                WHERE `mail_template_id` = :templateId
                    AND `language_id` = :languageId
                    AND `content_html` LIKE :staleToken',
            [
                'html' => $this->loadTemplateFile($htmlFile),
                'plain' => $this->loadTemplateFile($txtFile),
                'now' => $now,
                'templateId' => $templateId,
                'languageId' => $languageId,
                'staleToken' => self::STALE_TOKEN,
            ]
        );
    }

    private function loadTemplateFile(string $relativePath): string
    {
        $path = self::TEMPLATE_DIR . '/' . $relativePath;
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException(sprintf('Mail template file "%s" could not be read', $path));
        }

        return $contents;
    }

    private function getLanguageIdByLocale(Connection $connection, string $locale): ?string
    {
        $languageId = $connection->fetchOne(
            'SELECT `language`.`id` FROM `language`
                INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
                WHERE `locale`.`code` = :code',
            ['code' => $locale]
        );

        if (! is_string($languageId) || $languageId === '') {
            return null;
        }

        return $languageId;
    }
}
