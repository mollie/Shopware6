<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Fixture\Rule;

use Mollie\Shopware\Component\Fixture\AbstractFixture;
use Mollie\Shopware\Component\Fixture\FixtureGroup;
use Mollie\Shopware\Component\Subscription\Rule\CartSubscriptionRule;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CartSubscriptionRuleFixture extends AbstractFixture
{
    private const RULE_NAME = 'Cart has subscription products';

    /**
     * @param EntityRepository<RuleCollection<RuleEntity>> $ruleRepository
     */
    public function __construct(
        #[Autowire(service: 'rule.repository')]
        private readonly EntityRepository $ruleRepository,
    ) {
    }

    public function getGroup(): FixtureGroup
    {
        return FixtureGroup::DATA;
    }

    public function install(Context $context): void
    {
        $this->ruleRepository->upsert([
            [
                'id' => $this->getId('rule'),
                'name' => self::RULE_NAME,
                'priority' => 10,
                'conditions' => [
                    [
                        'id' => $this->getId('or'),
                        'type' => 'orContainer',
                        'position' => 0,
                        'children' => [
                            [
                                'id' => $this->getId('and'),
                                'type' => 'andContainer',
                                'position' => 0,
                                'children' => [
                                    [
                                        'id' => $this->getId('condition'),
                                        'type' => CartSubscriptionRule::RULE_NAME,
                                        'position' => 0,
                                        'value' => [
                                            'isSubscription' => true,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $context);
    }

    public function uninstall(Context $context): void
    {
        $this->ruleRepository->delete([
            ['id' => $this->getId('rule')],
        ], $context);
    }

    private function getId(string $key): string
    {
        return Uuid::fromStringToHex('mollie-cart-subscription-' . $key);
    }
}
