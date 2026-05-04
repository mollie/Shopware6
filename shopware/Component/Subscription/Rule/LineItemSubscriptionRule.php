<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Rule;

use Mollie\Shopware\Entity\Product\Product;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraints\Type;

#[AutoconfigureTag('shopware.rule.definition')]
final class LineItemSubscriptionRule extends Rule
{
    protected bool $isSubscription = false;

    public function getName(): string
    {
        return 'mollie_lineitem_subscription_rule';
    }

    public function match(RuleScope $scope): bool
    {
        if (! $scope instanceof LineItemScope) {
            return false;
        }

        $isItemSubscription = $this->isItemSubscription($scope->getLineItem());

        return $this->isSubscription ? $isItemSubscription : ! $isItemSubscription;
    }

    /**
     * @return Type[][]
     */
    public function getConstraints(): array
    {
        return [
            'isSubscription' => [new Type('bool')],
        ];
    }

    private function isItemSubscription(LineItem $lineItem): bool
    {
        $extension = $lineItem->getExtension(Mollie::EXTENSION);

        return $extension instanceof Product && $extension->isSubscription() === true;
    }
}
