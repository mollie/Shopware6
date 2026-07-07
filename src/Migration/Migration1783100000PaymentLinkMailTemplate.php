<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Registers a dedicated "Mollie Payment Link" mail template type and template. It ships a working
 * example that renders the payment link URL via rawUrl(), so merchants can either use it directly
 * in a flow or copy the snippet into their own order mails.
 *
 * @internal
 */
final class Migration1783100000PaymentLinkMailTemplate extends MigrationStep
{
    private const TECHNICAL_NAME = 'mollie_payment_link';

    private const TEMPLATE_DIR = __DIR__ . '/../Resources/views/mail/PaymentLink';

    public function getCreationTimestamp(): int
    {
        return 1783100000;
    }

    public function update(Connection $connection): void
    {
        $mailTemplateTypeId = $this->ensureMailTemplateType($connection);
        $this->ensureMailTemplate($connection, $mailTemplateTypeId);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function ensureMailTemplateType(Connection $connection): string
    {
        $existingId = $connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :name LIMIT 1',
            ['name' => self::TECHNICAL_NAME]
        );

        if (is_string($existingId) && $existingId !== '') {
            return Uuid::fromBytesToHex($existingId);
        }

        $mailTemplateTypeId = Uuid::randomHex();
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $connection->executeStatement(
            'INSERT IGNORE INTO `mail_template_type` (`id`, `technical_name`, `available_entities`, `created_at`)
                VALUES (:id, :technicalName, :availableEntities, :createdAt)',
            [
                'id' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'technicalName' => self::TECHNICAL_NAME,
                'availableEntities' => json_encode([
                    'order' => 'order',
                    'salesChannel' => 'sales_channel',
                ], JSON_THROW_ON_ERROR),
                'createdAt' => $now,
            ]
        );

        $this->insertTypeTranslations($connection, $mailTemplateTypeId, $now);

        return $mailTemplateTypeId;
    }

    private function insertTypeTranslations(Connection $connection, string $mailTemplateTypeId, string $now): void
    {
        $englishName = 'Payment Link (Mollie)';
        $germanName = 'Zahlungslink (Mollie)';

        $enLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deLangId = $this->getLanguageIdByLocale($connection, 'de-DE');

        if ($enLangId !== null) {
            $this->insertTypeTranslation($connection, $mailTemplateTypeId, $enLangId, $englishName, $now);
        }
        if ($deLangId !== null) {
            $this->insertTypeTranslation($connection, $mailTemplateTypeId, $deLangId, $germanName, $now);
        }

        $systemLangId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        if ($systemLangId !== $enLangId && $systemLangId !== $deLangId) {
            $this->insertTypeTranslation($connection, $mailTemplateTypeId, $systemLangId, $englishName, $now);
        }
    }

    private function insertTypeTranslation(Connection $connection, string $mailTemplateTypeId, string $languageId, string $name, string $now): void
    {
        $connection->executeStatement(
            'INSERT IGNORE INTO `mail_template_type_translation`
                (`mail_template_type_id`, `language_id`, `name`, `created_at`)
                VALUES (:typeId, :languageId, :name, :createdAt)',
            [
                'typeId' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'languageId' => $languageId,
                'name' => $name,
                'createdAt' => $now,
            ]
        );
    }

    private function ensureMailTemplate(Connection $connection, string $mailTemplateTypeId): void
    {
        $existingTemplateId = $connection->fetchOne(
            'SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :typeId LIMIT 1',
            ['typeId' => Uuid::fromHexToBytes($mailTemplateTypeId)]
        );

        if (is_string($existingTemplateId) && $existingTemplateId !== '') {
            return;
        }

        $mailTemplateId = Uuid::randomHex();
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $connection->executeStatement(
            'INSERT IGNORE INTO `mail_template`
                (`id`, `mail_template_type_id`, `system_default`, `created_at`)
                VALUES (:id, :typeId, 0, :createdAt)',
            [
                'id' => Uuid::fromHexToBytes($mailTemplateId),
                'typeId' => Uuid::fromHexToBytes($mailTemplateTypeId),
                'createdAt' => $now,
            ]
        );

        $sender = '{{ salesChannel.name }}';
        $enLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deLangId = $this->getLanguageIdByLocale($connection, 'de-DE');

        $en = [
            'subject' => 'Complete your payment for order {{ order.orderNumber }}',
            'description' => 'Mollie Payment Link Mail',
            'contentHtml' => $this->loadTemplateFile('en.html'),
            'contentPlain' => $this->loadTemplateFile('en.txt'),
        ];

        $de = [
            'subject' => 'Schließen Sie Ihre Zahlung für Bestellung {{ order.orderNumber }} ab',
            'description' => 'Mollie Zahlungslink Mail',
            'contentHtml' => $this->loadTemplateFile('de.html'),
            'contentPlain' => $this->loadTemplateFile('de.txt'),
        ];

        if ($enLangId !== null) {
            $this->insertTemplateTranslation($connection, $mailTemplateId, $enLangId, $sender, $en, $now);
        }
        if ($deLangId !== null) {
            $this->insertTemplateTranslation($connection, $mailTemplateId, $deLangId, $sender, $de, $now);
        }

        $systemLangId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        if ($systemLangId !== $enLangId && $systemLangId !== $deLangId) {
            $this->insertTemplateTranslation($connection, $mailTemplateId, $systemLangId, $sender, $en, $now);
        }
    }

    /**
     * @param array{subject:string,description:string,contentHtml:string,contentPlain:string} $translation
     */
    private function insertTemplateTranslation(Connection $connection, string $mailTemplateId, string $languageId, string $sender, array $translation, string $now): void
    {
        $connection->executeStatement(
            'INSERT IGNORE INTO `mail_template_translation`
                (`mail_template_id`, `language_id`, `sender_name`, `subject`, `description`, `content_html`, `content_plain`, `created_at`)
                VALUES (:templateId, :languageId, :sender, :subject, :description, :contentHtml, :contentPlain, :createdAt)',
            [
                'templateId' => Uuid::fromHexToBytes($mailTemplateId),
                'languageId' => $languageId,
                'sender' => $sender,
                'subject' => $translation['subject'],
                'description' => $translation['description'],
                'contentHtml' => $translation['contentHtml'],
                'contentPlain' => $translation['contentPlain'],
                'createdAt' => $now,
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
