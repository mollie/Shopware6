<?php
declare(strict_types=1);

namespace Mollie\Shopware\Behat\Context;

use Behat\Step\Then;
use Mollie\Shopware\Behat\Storage;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\SubscriptionActionHandler;
use Mollie\Shopware\Integration\Data\SubscriptionTestBehaviour;
use PHPUnit\Framework\Assert;

final class SubscriptionContext extends ShopwareContext
{
    use SubscriptionTestBehaviour;
    public const STORAGE_SUBSCRIPTION = 'subscription';

    #[Then('i :arg1 the subscription')]
    public function iTheSubscription(string $action): void
    {
        /** @var SubscriptionEntity $subscription */
        $subscription = Storage::get(self::STORAGE_SUBSCRIPTION);

        $context = $this->getCurrentSalesChannelContext()->getContext();
        /** @var SubscriptionActionHandler $actionHandler */
        $actionHandler = $this->getContainer()->get(SubscriptionActionHandler::class);
        $actionHandler->handle($action,$subscription->getId(),$context);
    }

    #[Then('the subscription status is :arg1')]
    public function theSubscriptionStatusIs(string $status): void
    {
        $orderId = Storage::get('orderId');
        $context = $this->getCurrentSalesChannelContext()->getContext();

        $subscription = $this->getOrderSubscriptions($orderId, $context)->first();

        Assert::assertNotNull($subscription, sprintf('No subscription found for order %s', $orderId));

        Storage::set(self::STORAGE_SUBSCRIPTION, $subscription);

        Assert::assertSame($status, $subscription->getStatus());
    }

    #[Then('all subscriptions of the order have a mollie id')]
    public function allSubscriptionsOfTheOrderHaveAMollieId(): void
    {
        $orderId = Storage::get('orderId');
        $context = $this->getCurrentSalesChannelContext()->getContext();

        $subscriptions = $this->getOrderSubscriptions($orderId, $context);

        Assert::assertSame(2, $subscriptions->count(), sprintf('Expected 2 subscriptions for order %s, got %d', $orderId, $subscriptions->count()));

        foreach ($subscriptions as $subscription) {
            Assert::assertNotEmpty(
                $subscription->getMollieId(),
                sprintf('Subscription %s has no Mollie id', $subscription->getId())
            );
        }
    }
}
