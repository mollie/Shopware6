<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Fixture\FlowBuilder;

use Mollie\Shopware\Component\Fixture\AbstractFixture;
use Mollie\Shopware\Component\Fixture\FixtureGroup;
use Shopware\Core\Content\Flow\FlowCollection;
use Shopware\Core\Content\Flow\FlowEntity;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeCollection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventDefinition;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Test-only fixture. For every Mollie flow event that the plugin exposes to the
 * Flow Builder (see BusinessEventSubscriber) it creates:
 *   - a mail template type + a mail template with a simple static text
 *   - an active flow with an "action.mail.send" sequence
 *
 * The goal is manual verification: after loading this fixture, triggering any
 * Mollie flow event on a test system sends a plain mail like
 * "Flow event mollie.webhook_received.All triggered", so you can immediately see
 * that the event is registered and the flow actually fires.
 *
 * The event list is read live from the BusinessEventCollector, so it always
 * matches whatever the plugin registers today - no hardcoded list to maintain.
 *
 * This is intentionally a fixture (opt-in via `mollie:fixtures:load`), NOT a
 * migration, so these test flows never ship to production systems.
 */
final class FlowEventMailFixture extends AbstractFixture
{
    private const EVENT_NAME_PREFIX = 'mollie.';

    /**
     * @param EntityRepository<MailTemplateTypeCollection<MailTemplateTypeEntity>> $mailTemplateTypeRepository
     * @param EntityRepository<MailTemplateCollection<MailTemplateEntity>> $mailTemplateRepository
     * @param EntityRepository<FlowCollection<FlowEntity>> $flowRepository
     */
    public function __construct(
        private readonly BusinessEventCollector $businessEventCollector,
        #[Autowire(service: 'mail_template_type.repository')]
        private readonly EntityRepository $mailTemplateTypeRepository,
        #[Autowire(service: 'mail_template.repository')]
        private readonly EntityRepository $mailTemplateRepository,
        #[Autowire(service: 'flow.repository')]
        private readonly EntityRepository $flowRepository,
    ) {
    }

    public function getGroup(): FixtureGroup
    {
        return FixtureGroup::DATA;
    }

    public function install(Context $context): void
    {
        foreach ($this->getMollieFlowEvents($context) as $eventName) {
            $typeId = $this->mailTemplateTypeId($eventName);
            $templateId = $this->mailTemplateId($eventName);

            $this->mailTemplateTypeRepository->upsert([
                [
                    'id' => $typeId,
                    'technicalName' => $this->technicalName($eventName),
                    'availableEntities' => [],
                    'name' => 'Mollie Flow Test: ' . $eventName,
                    'translations' => [
                        Defaults::LANGUAGE_SYSTEM => [
                            'name' => 'Mollie Flow Test: ' . $eventName,
                        ],
                    ],
                ],
            ], $context);

            $this->mailTemplateRepository->upsert([
                [
                    'id' => $templateId,
                    'mailTemplateTypeId' => $typeId,
                    'systemDefault' => false,
                    'senderName' => 'Mollie Flow Test',
                    'subject' => 'Mollie Flow Test: ' . $eventName,
                    'description' => 'Test template for flow event ' . $eventName,
                    'contentHtml' => '<p>Flow event <strong>' . $eventName . '</strong> triggered</p>',
                    'contentPlain' => 'Flow event ' . $eventName . ' triggered',
                    'translations' => [
                        Defaults::LANGUAGE_SYSTEM => [
                            'senderName' => 'Mollie Flow Test',
                            'subject' => 'Mollie Flow Test: ' . $eventName,
                            'description' => 'Test template for flow event ' . $eventName,
                            'contentHtml' => '<p>Flow event <strong>' . $eventName . '</strong> triggered</p>',
                            'contentPlain' => 'Flow event ' . $eventName . ' triggered',
                        ],
                    ],
                ],
            ], $context);

            $this->flowRepository->upsert([
                [
                    'id' => $this->flowId($eventName),
                    'name' => 'Mollie Flow Test: ' . $eventName,
                    'eventName' => $eventName,
                    'priority' => 1,
                    'active' => true,
                    'sequences' => [
                        [
                            'id' => $this->flowSequenceId($eventName),
                            'actionName' => 'action.mail.send',
                            'position' => 1,
                            'displayGroup' => 1,
                            'trueCase' => false,
                            'config' => [
                                'mailTemplateId' => $templateId,
                                'mailTemplateTypeId' => $typeId,
                                'recipient' => ['type' => 'default', 'data' => []],
                            ],
                        ],
                    ],
                ],
            ], $context);
        }
    }

    public function uninstall(Context $context): void
    {
        $flowIds = [];
        $templateIds = [];
        $typeIds = [];

        foreach ($this->getMollieFlowEvents($context) as $eventName) {
            $flowIds[] = ['id' => $this->flowId($eventName)];
            $templateIds[] = ['id' => $this->mailTemplateId($eventName)];
            $typeIds[] = ['id' => $this->mailTemplateTypeId($eventName)];
        }

        if (count($flowIds) === 0) {
            return;
        }

        // Order matters: flow (and its sequences) first, then templates, then types (FK).
        $this->flowRepository->delete($flowIds, $context);
        $this->mailTemplateRepository->delete($templateIds, $context);
        $this->mailTemplateTypeRepository->delete($typeIds, $context);
    }

    /**
     * @return string[]
     */
    private function getMollieFlowEvents(Context $context): array
    {
        $definitions = $this->businessEventCollector->collect($context);

        $eventNames = [];
        foreach ($definitions as $definition) {
            /** @var BusinessEventDefinition $definition */
            $eventName = $definition->getName();
            if (! str_starts_with($eventName, self::EVENT_NAME_PREFIX)) {
                continue;
            }
            // action.mail.send only sends for MailAware events; skip anything else.
            if (! is_a($definition->getClass(), MailAware::class, true)) {
                continue;
            }
            $eventNames[] = $eventName;
        }

        return $eventNames;
    }

    private function technicalName(string $eventName): string
    {
        return 'mollie_flow_test_' . str_replace('.', '_', $eventName);
    }

    private function mailTemplateTypeId(string $eventName): string
    {
        return Uuid::fromStringToHex('mollie-flow-test-mtt-' . $eventName);
    }

    private function mailTemplateId(string $eventName): string
    {
        return Uuid::fromStringToHex('mollie-flow-test-mt-' . $eventName);
    }

    private function flowId(string $eventName): string
    {
        return Uuid::fromStringToHex('mollie-flow-test-flow-' . $eventName);
    }

    private function flowSequenceId(string $eventName): string
    {
        return Uuid::fromStringToHex('mollie-flow-test-seq-' . $eventName);
    }
}
