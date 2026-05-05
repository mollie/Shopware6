<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Action;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Gateway\SubscriptionGateway;
use Mollie\Shopware\Component\Mollie\Gateway\SubscriptionGatewayInterface;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\SequenceType;
use Mollie\Shopware\Component\Payment\PaymentHandlerLocator;
use Mollie\Shopware\Component\Router\RouteBuilder;
use Mollie\Shopware\Component\Router\RouteBuilderInterface;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class UpdatePaymentMethodAction
{
    /**
     * @param EntityRepository<SubscriptionCollection<SubscriptionEntity>> $subscriptionRepository
     */
    public function __construct(
        #[Autowire(service: 'mollie_subscription.repository')]
        private readonly EntityRepository $subscriptionRepository,
        #[Autowire(service: SubscriptionGateway::class)]
        private readonly SubscriptionGatewayInterface $subscriptionGateway,
        #[Autowire(service: MollieGateway::class)]
        private readonly MollieGatewayInterface $mollieGateway,
        #[Autowire(service: RouteBuilder::class)]
        private readonly RouteBuilderInterface $routeBuilder,
        private readonly PaymentHandlerLocator $paymentHandlerLocator,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
    }

    public function start(SubscriptionEntity $subscription, string $orderNumber, string $redirectUrl, Context $context): Payment
    {
        $salesChannelId = $subscription->getSalesChannelId();
        $mollieCustomerId = $subscription->getMollieCustomerId();

        $subscriptionPayments = $this->mollieGateway->listSubscriptionPayments(
            $mollieCustomerId,
            $subscription->getMollieId(),
            $orderNumber,
            $salesChannelId
        );

        foreach ($subscriptionPayments->filterCancelable() as $cancelablePayment) {
            $this->mollieGateway->cancelPayment($cancelablePayment->getId(), $orderNumber, $salesChannelId);
        }

        if ($redirectUrl === '') {
            $redirectUrl = $this->routeBuilder->getSubscriptionPaymentUpdateReturnUrl($subscription->getId());
        }

        $createPayment = new CreatePayment(
            'Update Subscription Payment: ' . $subscription->getDescription(),
            $redirectUrl,
            new Money(0.0, 'EUR')
        );
        $createPayment->setSequenceType(SequenceType::FIRST);
        $createPayment->setCustomerId($mollieCustomerId);
        $createPayment->setMethods($this->collectSubscriptionMethods());

        $webhookUrl = $this->routeBuilder->getSubscriptionPaymentUpdateWebhookUrl($subscription->getId());
        if ($webhookUrl !== '') {
            $createPayment->setWebhookUrl($webhookUrl);
        }

        $payment = $this->mollieGateway->createPayment($createPayment, $salesChannelId);

        $metadata = $subscription->getMetadata();
        $metadata->setTmpTransaction($payment->getId());

        $this->subscriptionRepository->upsert([[
            'id' => $subscription->getId(),
            'metadata' => $metadata->toArray(),
        ]], $context);

        $this->logger->info('Subscription payment update started', [
            'subscriptionId' => $subscription->getId(),
            'molliePaymentId' => $payment->getId(),
            'salesChannelId' => $salesChannelId,
        ]);

        return $payment;
    }

    public function confirm(SubscriptionEntity $subscription, string $orderNumber, Context $context): void
    {
        $salesChannelId = $subscription->getSalesChannelId();
        $tmpTransactionId = $subscription->getMetadata()->getTmpTransaction();

        if ($tmpTransactionId === '') {
            throw UpdatePaymentMethodActionException::missingTmpTransaction($subscription->getId());
        }

        $payment = $this->mollieGateway->getPayment($tmpTransactionId, $orderNumber, $salesChannelId);

        if (! $payment->getStatus()->isApproved()) {
            throw UpdatePaymentMethodActionException::paymentNotApproved($subscription->getId(), $payment->getId(), $payment->getStatus()->value);
        }

        $newMandateId = (string) $payment->getMandateId();
        if ($newMandateId === '') {
            throw UpdatePaymentMethodActionException::paymentWithoutMandate($subscription->getId(), $payment->getId());
        }

        $mollieSubscription = $this->subscriptionGateway->getSubscription(
            $subscription->getMollieId(),
            $subscription->getMollieCustomerId(),
            $orderNumber,
            $salesChannelId
        );
        $mollieSubscription->setMandateId($newMandateId);
        $this->subscriptionGateway->updateSubscription($mollieSubscription, $subscription->getMollieCustomerId(), $orderNumber, $salesChannelId);

        $metadata = $subscription->getMetadata();
        $metadata->setTmpTransaction('');

        $this->subscriptionRepository->upsert([[
            'id' => $subscription->getId(),
            'mandateId' => $newMandateId,
            'metadata' => $metadata->toArray(),
            'historyEntries' => [[
                'statusFrom' => '',
                'statusTo' => '',
                'comment' => 'payment method updated',
                'mollieId' => $subscription->getMollieId(),
            ]],
        ]], $context);

        $this->logger->info('Subscription payment update confirmed', [
            'subscriptionId' => $subscription->getId(),
            'newMandateId' => $newMandateId,
            'salesChannelId' => $salesChannelId,
        ]);
    }

    /**
     * @return list<string>
     */
    private function collectSubscriptionMethods(): array
    {
        $methods = [];
        foreach ($this->paymentHandlerLocator->getSubscriptionMethods() as $handler) {
            $methods[] = $handler->getPaymentMethod()->value;
        }

        return array_values(array_unique($methods));
    }
}
