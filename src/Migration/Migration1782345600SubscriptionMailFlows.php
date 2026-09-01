<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Ships flow templates and default flows for the Mollie mail templates,
 * following the Shopware Commercial pattern (SubscriptionFlowTemplates):
 * a Mollie-named `flow_template` (so merchants can build their own flow from
 * it) plus an active `flow` with an "action.mail.send" sequence (so the mail
 * is sent out of the box). Dispatching the MailAware event alone sends nothing
 * in Shopware — a flow with a mail-send action is required.
 *
 * @internal
 */
final class Migration1782345600SubscriptionMailFlows extends MigrationStep
{
    /**
     * @var array<int,array{eventName:string,templateType:string,flowName:string}>
     */
    private const FLOWS = [
        [
            'eventName' => 'mollie.subscription.renewal_reminder',
            'templateType' => 'mollie_subscriptions_renewal_reminder',
            'flowName' => 'Mollie Subscription Renewal Reminder',
        ],
        [
            'eventName' => 'mollie.subscription.priceChangeNotice',
            'templateType' => 'mollie_subscription_price_change',
            'flowName' => 'Mollie Subscription Price Change Notice',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1782345600;
    }

    public function update(Connection $connection): void
    {
        $existingTemplates = $this->getExistingFlowTemplateEvents($connection);
        $created = false;

        foreach (self::FLOWS as $flow) {
            $mailTemplate = $this->findMailTemplate($connection, $flow['templateType']);
            if ($mailTemplate === null) {
                continue;
            }

            $sequenceConfig = [
                'mailTemplateId' => $mailTemplate['templateId'],
                'mailTemplateTypeId' => $mailTemplate['typeId'],
                'recipient' => ['data' => [], 'type' => 'default'],
            ];

            if (! in_array($flow['eventName'], $existingTemplates, true)) {
                $this->createFlowTemplate($connection, $flow['eventName'], $flow['flowName'], $sequenceConfig);
                $created = true;
            }

            if (! $this->flowExists($connection, $flow['eventName'])) {
                $this->createFlow($connection, $flow['eventName'], $flow['flowName'], $sequenceConfig);
                $created = true;
            }
        }

        if ($created) {
            $this->registerIndexer($connection, 'flow.indexer');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    /**
     * @param array<string,mixed> $sequenceConfig
     */
    private function createFlowTemplate(Connection $connection, string $eventName, string $name, array $sequenceConfig): void
    {
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $config = [
            'eventName' => $eventName,
            'description' => null,
            'customFields' => null,
            'sequences' => [[
                'id' => Uuid::randomHex(),
                'actionName' => 'action.mail.send',
                'config' => $sequenceConfig,
                'parentId' => null,
                'ruleId' => null,
                'position' => 1,
                'trueCase' => false,
                'displayGroup' => 1,
            ]],
        ];

        $connection->insert('flow_template', [
            'id' => Uuid::randomBytes(),
            'name' => $name,
            'config' => json_encode($config, JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);
    }

    /**
     * @param array<string,mixed> $sequenceConfig
     */
    private function createFlow(Connection $connection, string $eventName, string $name, array $sequenceConfig): void
    {
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $flowId = Uuid::randomBytes();

        $connection->insert('flow', [
            'id' => $flowId,
            'name' => $name,
            'event_name' => $eventName,
            'priority' => 1,
            'active' => 1,
            'invalid' => 0,
            'created_at' => $now,
        ]);

        $connection->insert('flow_sequence', [
            'id' => Uuid::randomBytes(),
            'flow_id' => $flowId,
            'rule_id' => null,
            'parent_id' => null,
            'action_name' => 'action.mail.send',
            'position' => 1,
            'true_case' => 0,
            'display_group' => 1,
            'config' => json_encode($sequenceConfig, JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);
    }

    /**
     * @return null|array{templateId:string,typeId:string}
     */
    private function findMailTemplate(Connection $connection, string $templateType): ?array
    {
        $typeByteId = $connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :name LIMIT 1',
            ['name' => $templateType]
        );
        if (! is_string($typeByteId) || $typeByteId === '') {
            return null;
        }

        $templateByteId = $connection->fetchOne(
            'SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :typeId LIMIT 1',
            ['typeId' => $typeByteId]
        );
        if (! is_string($templateByteId) || $templateByteId === '') {
            return null;
        }

        return [
            'templateId' => Uuid::fromBytesToHex($templateByteId),
            'typeId' => Uuid::fromBytesToHex($typeByteId),
        ];
    }

    /**
     * @return string[]
     */
    private function getExistingFlowTemplateEvents(Connection $connection): array
    {
        return $connection->fetchFirstColumn(
            'SELECT JSON_UNQUOTE(JSON_EXTRACT(`config`, \'$.eventName\')) FROM `flow_template`'
        );
    }

    private function flowExists(Connection $connection, string $eventName): bool
    {
        return (bool) $connection->fetchOne(
            'SELECT 1 FROM `flow` WHERE `event_name` = :eventName LIMIT 1',
            ['eventName' => $eventName]
        );
    }
}
