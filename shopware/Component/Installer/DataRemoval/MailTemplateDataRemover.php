<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Installer\DataRemoval;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;

/**
 * Removes the mail templates (and their types) that the plugin registers via migrations - the
 * subscription renewal reminder and the subscription price change notification. Translations are
 * removed automatically via the cascading foreign keys.
 */
final class MailTemplateDataRemover implements DataRemoverInterface
{
    private const MAIL_TEMPLATE_TYPE_TECHNICAL_NAMES = [
        'mollie_subscriptions_renewal_reminder',
        'mollie_subscription_price_change',
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function remove(Context $context): void
    {
        $typeIds = $this->connection->fetchFirstColumn(
            'SELECT id FROM mail_template_type WHERE technical_name IN (:names)',
            ['names' => self::MAIL_TEMPLATE_TYPE_TECHNICAL_NAMES],
            ['names' => ArrayParameterType::STRING]
        );

        if (count($typeIds) === 0) {
            return;
        }

        $this->connection->executeStatement(
            'DELETE FROM mail_template WHERE mail_template_type_id IN (:ids)',
            ['ids' => $typeIds],
            ['ids' => ArrayParameterType::BINARY]
        );

        $this->connection->executeStatement(
            'DELETE FROM mail_template_type WHERE id IN (:ids)',
            ['ids' => $typeIds],
            ['ids' => ArrayParameterType::BINARY]
        );
    }
}
