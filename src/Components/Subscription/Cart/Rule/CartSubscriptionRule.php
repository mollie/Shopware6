<?php

namespace Kiener\MolliePayments\Components\Subscription\Cart\Rule;


use Kiener\MolliePayments\Struct\LineItem\LineItemAttributes;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Type;


class CartSubscriptionRule extends Rule
{

    /**
     * @var bool
     */
    protected bool $isSubscription;


    /**
     */
    public function __construct()
    {
        parent::__construct();

        $this->isSubscription = false;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'mollie_cart_subscription_rule';
    }

    /**
     * @param RuleScope $scope
     * @return bool
     */
    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        $hasCartSubscription = false;

        foreach ($scope->getCart()->getLineItems() as $item) {

            $tmpSubscription = $this->isItemSubscription($item);

            if ($tmpSubscription) {
                $hasCartSubscription = true;
                break;

            }
        }

        if ($this->isSubscription) {
            return $hasCartSubscription;
        }

        return !$hasCartSubscription;
    }

    /**
     * @return Type[][]
     */
    public function getConstraints(): array
    {
        return [
            'isSubscription' => [new Type('bool')]
        ];
    }

    /**
     * @param LineItem $lineItem
     * @return bool
     */
    private function isItemSubscription(LineItem $lineItem): bool
    {
        try {

            $attributes = new LineItemAttributes($lineItem);

            return $attributes->isSubscriptionProduct();

        } catch (\Exception $e) {
            return false;
        }
    }

}
