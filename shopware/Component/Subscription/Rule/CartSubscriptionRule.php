<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Rule;

use Mollie\Shopware\Entity\Product\Product;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Validator\Constraints\Type;

#[AutoconfigureTag('shopware.rule.definition')]
class CartSubscriptionRule extends Rule
{
    /**
     * @var bool
     */
    protected $isSubscription = false;

    public function getName(): string
    {
        return 'mollie_cart_subscription_rule';
    }

    public function match(RuleScope $scope): bool
    {
        if (! $scope instanceof CartRuleScope) {
            return false;
        }

        $hasCartSubscription = false;
        foreach ($scope->getCart()->getLineItems() as $item) {
            if ($this->isItemSubscription($item)) {
                $hasCartSubscription = true;
                break;
            }
        }

        return $this->isSubscription ? $hasCartSubscription : ! $hasCartSubscription;
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
