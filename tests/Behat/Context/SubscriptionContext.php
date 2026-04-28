<?php
declare(strict_types=1);

namespace Mollie\Shopware\Behat\Context;

use Behat\Step\Then;
use Behat\Step\When;
use Mollie\Shopware\Behat\Storage;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\Route\WebhookRoute;
use Mollie\Shopware\Component\Subscription\SubscriptionActionHandler;
use Mollie\Shopware\Integration\Data\SubscriptionTestBehaviour;
use PHPUnit\Framework\Assert;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\HttpFoundation\Request;

final class SubscriptionContext extends ShopwareContext
{
    use SubscriptionTestBehaviour;
    public const STORAGE_SUBSCRIPTION = 'subscription';
    public const STORAGE_REMEMBERED_SUBSCRIPTION_ID = 'rememberedSubscriptionId';

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

    #[Then('i remember the subscription for renewal')]
    public function iRememberTheSubscriptionForRenewal(): void
    {
        $orderId = Storage::get(CheckoutContext::STORAGE_ORDER_ID);
        $context = $this->getCurrentSalesChannelContext()->getContext();

        $subscription = $this->getOrderSubscriptions($orderId, $context)->first();
        Assert::assertNotNull($subscription, sprintf('No subscription found for order %s', $orderId));

        Storage::set(self::STORAGE_REMEMBERED_SUBSCRIPTION_ID, $subscription->getId());
    }

    #[When('i trigger the subscription renewal webhook')]
    public function iTriggerTheSubscriptionRenewalWebhook(): void
    {
        $subscriptionId = Storage::get(self::STORAGE_REMEMBERED_SUBSCRIPTION_ID);
        $paymentId = Storage::get(CheckoutContext::STORAGE_REMEMBERED_PAYMENT_ID);

        $context = $this->getCurrentSalesChannelContext()->getContext();

        /** @var WebhookRoute $webhookRoute */
        $webhookRoute = $this->getContainer()->get(WebhookRoute::class);

        $request = new Request();
        $request->query->set('id', $paymentId);

        $response = $webhookRoute->notify($subscriptionId, $request, $context);

        $renewalTransaction = $response->getPayment()->getShopwareTransaction();
        $renewalOrderId = $renewalTransaction->getOrderId();

        Assert::assertNotEmpty($renewalOrderId, 'Renewal transaction has no order id');

        Storage::set(CheckoutContext::STORAGE_ORDER_ID, $renewalOrderId);
    }

    #[Then('the subscription has been renewed')]
    public function theSubscriptionHasBeenRenewed(): void
    {
        $subscriptionId = Storage::get(self::STORAGE_REMEMBERED_SUBSCRIPTION_ID);
        $context = $this->getCurrentSalesChannelContext()->getContext();

        /** @var EntityRepository<SubscriptionCollection<SubscriptionEntity>> $repository */
        $repository = $this->getContainer()->get('mollie_subscription.repository');

        $criteria = new Criteria([$subscriptionId]);
        $criteria->addAssociation('historyEntries');

        /** @var ?SubscriptionEntity $subscription */
        $subscription = $repository->search($criteria, $context)->first();
        Assert::assertNotNull($subscription, sprintf('Subscription %s not found', $subscriptionId));

        $renewed = false;
        foreach ($subscription->getHistoryEntries() as $entry) {
            if ($entry->getComment() === 'renewed') {
                $renewed = true;
                break;
            }
        }

        Assert::assertTrue($renewed, sprintf('Subscription %s has no "renewed" history entry', $subscriptionId));
    }
}
