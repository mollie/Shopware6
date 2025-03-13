<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\Rule;

use Kiener\MolliePayments\Struct\LineItem\LineItemAttributes;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Type;

class CartSubscriptionRule extends Rule
{
    /**
     * @var bool
     */
    protected $isSubscription;

    public function __construct()
    {
        parent::__construct();

        $this->isSubscription = false;
    }

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

        if ($scope->getCart()->getLineItems() instanceof LineItemCollection) {
            foreach ($scope->getCart()->getLineItems() as $item) {
                $tmpSubscription = $this->isItemSubscription($item);
                if ($tmpSubscription) {
                    $hasCartSubscription = true;
                    break;
                }
            }
        }

        $lookingForSubscription = $this->isSubscription;

        if ($lookingForSubscription) {
            return $hasCartSubscription;
        }

        return ! $hasCartSubscription;
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
        try {
            $attributes = new LineItemAttributes($lineItem);

            return $attributes->isSubscriptionProduct();
        } catch (\Exception $e) {
            return false;
        }
    }
}
