<?php

namespace Kiener\MolliePayments\Components\Subscription\Rule;

use Kiener\MolliePayments\Struct\LineItem\LineItemAttributes;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Type;

class LineItemSubscriptionRule extends Rule
{
    /**
     * @var bool
     */
    protected $isSubscription;


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
        return 'mollie_lineitem_subscription_rule';
    }

    /**
     * @param RuleScope $scope
     * @return bool
     */
    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof LineItemScope) {
            return false;
        }

        $isItemSubscription = $this->isItemSubscription($scope->getLineItem());

        if ($this->isSubscription) {
            return $isItemSubscription;
        }

        return !$isItemSubscription;
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
