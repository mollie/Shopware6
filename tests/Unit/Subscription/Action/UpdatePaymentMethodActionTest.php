<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Action;

use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentCollection;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Payment\PaymentHandlerLocator;
use Mollie\Shopware\Component\Subscription\Action\UpdatePaymentMethodAction;
use Mollie\Shopware\Component\Subscription\Action\UpdatePaymentMethodActionException;
use Mollie\Shopware\Component\Subscription\SubscriptionMetadata;
use Mollie\Shopware\Unit\Mollie\Fake\FakeRouteBuilder;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use Mollie\Shopware\Unit\Subscription\Builder\MollieSubscriptionBuilder;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionEntityBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionGateway;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Context;

#[CoversClass(UpdatePaymentMethodAction::class)]
final class UpdatePaymentMethodActionTest extends TestCase
{
    public function testStartCancelsCancelablePastPaymentsAndStoresTmpTransaction(): void
    {
        $cancelablePayment = new Payment('tr_cancelable');
        $cancelablePayment->setCancelable(true);

        $finalizedPayment = new Payment('tr_done');
        $finalizedPayment->setCancelable(false);

        $payments = new PaymentCollection();
        $payments->add($cancelablePayment);
        $payments->add($finalizedPayment);

        $subscriptionGateway = new FakeSubscriptionGateway();

        $newPayment = new Payment('tr_new');
        $newPayment->setCheckoutUrl('https://mollie.test/checkout');

        $mollieGateway = new FakeGateway(payment: $newPayment);
        $mollieGateway->registerSubscriptionPayments('sub_test123', $payments);

        $repository = new FakeSubscriptionRepository();

        $subscription = SubscriptionEntityBuilder::create()
            ->withId('subscription-id')
            ->withMollieId('sub_test123')
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->withMetadata(new SubscriptionMetadata('2026-06-01', 1, IntervalUnit::MONTHS))
            ->build()
        ;

        $action = new UpdatePaymentMethodAction(
            $repository,
            $subscriptionGateway,
            $mollieGateway,
            new FakeRouteBuilder(subscriptionPaymentUpdateReturnUrl: 'https://shop.test/return'),
            new PaymentHandlerLocator([]),
            new NullLogger()
        );

        $payment = $action->start($subscription, '10000', '', Context::createDefaultContext());

        $this->assertSame('tr_new', $payment->getId());
        $this->assertSame(['tr_cancelable'], $mollieGateway->getCancelledPaymentIds());
        $this->assertNotEmpty($mollieGateway->getCreatePayloads());
        $createPayload = $mollieGateway->getCreatePayloads()[0];
        $this->assertSame(0.0, (float) $createPayload->getAmount()->getValue());
        $this->assertSame('https://shop.test/return', $createPayload->getRedirectUrl());

        $this->assertSame(1, $repository->getUpsertCount());
        $upsert = $repository->getLastUpsert();
        $this->assertSame('subscription-id', $upsert['id']);
        $this->assertSame('tr_new', $upsert['metadata']['tmp_transaction']);
    }

    public function testConfirmThrowsWhenTmpTransactionIsMissing(): void
    {
        $action = new UpdatePaymentMethodAction(
            new FakeSubscriptionRepository(),
            new FakeSubscriptionGateway(),
            new FakeGateway(payment: new Payment('tr_unused')),
            new FakeRouteBuilder(),
            new PaymentHandlerLocator([]),
            new NullLogger()
        );

        $subscription = SubscriptionEntityBuilder::create()
            ->withId('subscription-id')
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->withMetadata(new SubscriptionMetadata('2026-06-01', 1, IntervalUnit::MONTHS))
            ->build()
        ;

        $this->expectException(UpdatePaymentMethodActionException::class);
        $this->expectExceptionMessage('No temporary transaction is registered');

        $action->confirm($subscription, '10000', Context::createDefaultContext());
    }

    public function testConfirmThrowsWhenPaymentNotApproved(): void
    {
        $payment = new Payment('tr_pending');
        $payment->setStatus(PaymentStatus::FAILED);

        $action = new UpdatePaymentMethodAction(
            new FakeSubscriptionRepository(),
            new FakeSubscriptionGateway(),
            new FakeGateway(payment: $payment),
            new FakeRouteBuilder(),
            new PaymentHandlerLocator([]),
            new NullLogger()
        );

        $metadata = new SubscriptionMetadata('2026-06-01', 1, IntervalUnit::MONTHS);
        $metadata->setTmpTransaction('tr_pending');

        $subscription = SubscriptionEntityBuilder::create()
            ->withId('subscription-id')
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->withMetadata($metadata)
            ->build()
        ;

        $this->expectException(UpdatePaymentMethodActionException::class);
        $this->expectExceptionMessage('is not in an approved state');

        $action->confirm($subscription, '10000', Context::createDefaultContext());
    }

    public function testConfirmUpdatesMandateOnSubscriptionAndPersistsResult(): void
    {
        $payment = new Payment('tr_paid');
        $payment->setStatus(PaymentStatus::PAID);
        $payment->setMandateId('mdt_new');

        $mollieSubscription = MollieSubscriptionBuilder::create()
            ->withId('sub_test123')
            ->build()
        ;

        $subscriptionGateway = new FakeSubscriptionGateway();
        $subscriptionGateway->register($mollieSubscription);

        $repository = new FakeSubscriptionRepository();

        $metadata = new SubscriptionMetadata('2026-06-01', 1, IntervalUnit::MONTHS);
        $metadata->setTmpTransaction('tr_paid');

        $subscription = SubscriptionEntityBuilder::create()
            ->withId('subscription-id')
            ->withMollieId('sub_test123')
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->withMetadata($metadata)
            ->build()
        ;

        $action = new UpdatePaymentMethodAction(
            $repository,
            $subscriptionGateway,
            new FakeGateway(payment: $payment),
            new FakeRouteBuilder(),
            new PaymentHandlerLocator([]),
            new NullLogger()
        );

        $action->confirm($subscription, '10000', Context::createDefaultContext());

        $this->assertSame(1, $subscriptionGateway->getCallCount('updateSubscription'));
        $this->assertSame('mdt_new', $mollieSubscription->getMandateId());

        $upsert = $repository->getLastUpsert();
        $this->assertSame('mdt_new', $upsert['mandateId']);
        $this->assertArrayNotHasKey('tmp_transaction', $upsert['metadata']);
        $this->assertSame('payment method updated', $upsert['historyEntries'][0]['comment']);
    }
}
