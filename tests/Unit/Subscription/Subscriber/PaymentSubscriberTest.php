<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Subscriber;

use Mollie\Shopware\Component\Mollie\Interval;
use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Payment\Event\PaymentCreatedEvent;
use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\Action\CreateAction;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzer;
use Mollie\Shopware\Component\Subscription\Subscriber\PaymentSubscriber;
use Mollie\Shopware\Component\Transaction\TransactionDataStruct;
use Mollie\Shopware\Entity\Product\Product;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionEntityBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionGroupCartBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionRepository;
use Mollie\Shopware\Unit\Transaction\Fake\FakeTransactionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

#[CoversClass(PaymentSubscriber::class)]
final class PaymentSubscriberTest extends TestCase
{
    public function testGetSubscribedEventsBindsPaymentCreatedEvent(): void
    {
        $subscribed = PaymentSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(PaymentCreatedEvent::class, $subscribed);
        $this->assertSame(['onPaymentCreated', PaymentSubscriber::PRIORITY], $subscribed[PaymentCreatedEvent::class]);
    }

    public function testOnPaymentCreatedSkipsWhenSubscriptionsAreDisabled(): void
    {
        $repository = new FakeSubscriptionRepository();
        $subscriber = $this->buildSubscriber($repository, enabled: false);

        $struct = $this->buildStructWithSubscriptionLineItems();

        $subscriber->onPaymentCreated($this->buildEvent($struct));

        $this->assertSame(0, $repository->getUpsertCount());
    }

    public function testOnPaymentCreatedSkipsWhenOrderAlreadyHasSubscriptions(): void
    {
        $repository = new FakeSubscriptionRepository();
        $subscriber = $this->buildSubscriber($repository, enabled: true);

        $struct = $this->buildStructWithSubscriptionLineItems();

        $existingCollection = new SubscriptionCollection();
        $existingCollection->add(SubscriptionEntityBuilder::create()->withId('existing-sub')->build());
        $struct->getOrder()->addExtension('mollieSubscriptions', $existingCollection);

        $subscriber->onPaymentCreated($this->buildEvent($struct));

        $this->assertSame(0, $repository->getUpsertCount());
    }

    public function testOnPaymentCreatedSkipsWhenOrderHasNoLineItems(): void
    {
        $repository = new FakeSubscriptionRepository();
        $subscriber = $this->buildSubscriber($repository, enabled: true);

        $service = new FakeTransactionService();
        $service->withNullLineItems();
        $service->createTransaction();
        $struct = $service->findById('tx-id', Context::createDefaultContext());

        $struct->getOrder()->setAmountNet(83.99);
        $struct->getOrder()->setTaxStatus('gross');
        $struct->getOrder()->setOrderDate(new \DateTimeImmutable('2026-04-30'));
        $struct->getOrder()->setVersionId('order-version-id');
        $struct->getOrder()->setCurrencyId('currency-id');

        // explicitly remove line items
        $reflection = new \ReflectionProperty($struct->getOrder(), 'lineItems');
        $reflection->setValue($struct->getOrder(), null);

        $subscriber->onPaymentCreated($this->buildEvent($struct));

        $this->assertSame(0, $repository->getUpsertCount());
    }

    public function testOnPaymentCreatedSkipsWhenNoSubscriptionLineItems(): void
    {
        $repository = new FakeSubscriptionRepository();
        $subscriber = $this->buildSubscriber($repository, enabled: true);

        $service = new FakeTransactionService();
        $service->createTransaction();
        $struct = $service->findById('tx-id', Context::createDefaultContext());
        $struct->getOrder()->setAmountNet(83.99);
        $struct->getOrder()->setTaxStatus('gross');
        $struct->getOrder()->setOrderDate(new \DateTimeImmutable('2026-04-30'));
        $struct->getOrder()->setVersionId('order-version-id');
        $struct->getOrder()->setCurrencyId('currency-id');

        $regular = new OrderLineItemEntity();
        $regular->setId('item-1');
        $regular->setLabel('Plain product');
        $regular->setQuantity(1);
        $regular->setPrice(new CalculatedPrice(10.0, 10.0, new CalculatedTaxCollection(), new TaxRuleCollection(), 1));
        $product = new Product();
        $product->setIsSubscription(false);
        $regular->addExtension(Mollie::EXTENSION, $product);
        $struct->getOrder()->setLineItems(new OrderLineItemCollection([$regular]));

        $subscriber->onPaymentCreated($this->buildEvent($struct));

        $this->assertSame(0, $repository->getUpsertCount());
    }

    public function testOnPaymentCreatedCreatesPendingSubscriptionPerIntervalGroup(): void
    {
        $repository = new FakeSubscriptionRepository();
        $subscriber = $this->buildSubscriber($repository, enabled: true);

        $struct = $this->buildStructWithSubscriptionLineItems();

        $subscriber->onPaymentCreated($this->buildEvent($struct));

        $this->assertSame(1, $repository->getUpsertCount());
        $payload = $repository->getLastUpsert();
        $this->assertSame(SubscriptionStatus::PENDING->value, $payload['status']);
        $this->assertSame('created', $payload['historyEntries'][0]['comment']);
    }

    public function testOnPaymentCreatedCreatesOneSubscriptionPerDistinctInterval(): void
    {
        $repository = new FakeSubscriptionRepository();
        $subscriber = $this->buildSubscriber($repository, enabled: true);

        $service = new FakeTransactionService();
        $service->createTransaction();
        $struct = $service->findById('tx-id', Context::createDefaultContext());
        $struct->getOrder()->setAmountNet(83.99);
        $struct->getOrder()->setTaxStatus('gross');
        $struct->getOrder()->setOrderDate(new \DateTimeImmutable('2026-04-30'));
        $struct->getOrder()->setVersionId('order-version-id');
        $struct->getOrder()->setCurrencyId('currency-id');

        $monthly = $this->orderLineItemWithMollieProduct('line-monthly', 1, IntervalUnit::MONTHS);
        $weekly = $this->orderLineItemWithMollieProduct('line-weekly', 2, IntervalUnit::WEEKS);
        $struct->getOrder()->setLineItems(new OrderLineItemCollection([$monthly, $weekly]));

        $subscriber->onPaymentCreated($this->buildEvent($struct));

        $this->assertSame(2, $repository->getUpsertCount());
    }

    private function buildSubscriber(FakeSubscriptionRepository $repository, bool $enabled): PaymentSubscriber
    {
        $settingsService = new FakeSettingsService(subscriptionSettings: new SubscriptionSettings(enabled: $enabled));
        $createAction = new CreateAction($repository, new NullLogger());

        return new PaymentSubscriber(
            $settingsService,
            new LineItemAnalyzer(),
            new FakeSubscriptionGroupCartBuilder(),
            $createAction,
            new NullLogger()
        );
    }

    private function buildStructWithSubscriptionLineItems(): TransactionDataStruct
    {
        $service = new FakeTransactionService();
        $service->createTransaction();
        $struct = $service->findById('tx-id', Context::createDefaultContext());
        $struct->getOrder()->setAmountNet(83.99);
        $struct->getOrder()->setTaxStatus('gross');
        $struct->getOrder()->setOrderDate(new \DateTimeImmutable('2026-04-30'));
        $struct->getOrder()->setVersionId('order-version-id');
        $struct->getOrder()->setCurrencyId('currency-id');

        $struct->getOrder()->setLineItems(new OrderLineItemCollection([
            $this->orderLineItemWithMollieProduct('line-1', 1, IntervalUnit::MONTHS),
            $this->orderLineItemWithMollieProduct('line-2', 1, IntervalUnit::MONTHS),
        ]));

        return $struct;
    }

    private function orderLineItemWithMollieProduct(string $id, int $intervalValue, IntervalUnit $intervalUnit): OrderLineItemEntity
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setId($id);
        $lineItem->setLabel('Subscription product');
        $lineItem->setQuantity(1);
        $lineItem->setReferencedId('product-' . $id);
        $lineItem->setPrice(new CalculatedPrice(10.0, 10.0, new CalculatedTaxCollection(), new TaxRuleCollection(), 1));

        $product = new Product();
        $product->setIsSubscription(true);
        $product->setInterval(new Interval($intervalValue, $intervalUnit));
        $lineItem->addExtension(Mollie::EXTENSION, $product);

        return $lineItem;
    }

    private function buildEvent(TransactionDataStruct $struct): PaymentCreatedEvent
    {
        return new PaymentCreatedEvent(
            'https://return',
            new Payment('payment-id'),
            $struct,
            new RequestDataBag(),
            Context::createDefaultContext()
        );
    }
}
