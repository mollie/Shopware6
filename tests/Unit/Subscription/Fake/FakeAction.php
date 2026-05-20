<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Fake;

use Mollie\Shopware\Component\Mollie\Subscription;
use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\Action\AbstractAction;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionCancelledEvent;
use Mollie\Shopware\Component\Subscription\SubscriptionDataStruct;
use Shopware\Core\Framework\Context;

final class FakeAction extends AbstractAction
{
    /** @var list<array{subscriptionId:string,orderNumber:string}> */
    private array $executions = [];

    /**
     * @param class-string<\Mollie\Shopware\Component\Subscription\Event\SubscriptionActionEvent> $eventClass
     */
    public function __construct(
        private readonly string $actionName,
        private readonly string $eventClass = SubscriptionCancelledEvent::class,
        private readonly ?Subscription $resultSubscription = null,
    ) {
    }

    public function getExecutionCount(): int
    {
        return count($this->executions);
    }

    /**
     * @return list<array{subscriptionId:string,orderNumber:string}>
     */
    public function getExecutions(): array
    {
        return $this->executions;
    }

    public function execute(SubscriptionDataStruct $subscriptionData, SubscriptionSettings $settings, Subscription $mollieSubscription, string $orderNumber, Context $context): Subscription
    {
        $this->executions[] = [
            'subscriptionId' => $subscriptionData->getSubscription()->getId(),
            'orderNumber' => $orderNumber,
        ];

        return $this->resultSubscription ?? $mollieSubscription;
    }

    public function getEventClass(): string
    {
        return $this->eventClass;
    }

    public static function getActioName(): string
    {
        throw new \LogicException('FakeAction does not provide a static action name; use the instance via supports().');
    }

    public function supports(string $actionName): bool
    {
        return $actionName === $this->actionName;
    }
}
