<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Route;

use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Subscription\Action\UpdatePaymentMethodAction;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\SubscriptionDataService;
use Mollie\Shopware\Component\Subscription\SubscriptionDataServiceInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['store-api']])]
final class UpdatePaymentMethodRoute extends AbstractUpdatePaymentMethodRoute
{
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        #[Autowire(service: SubscriptionDataService::class)]
        private readonly SubscriptionDataServiceInterface $subscriptionDataService,
        private readonly UpdatePaymentMethodAction $action
    ) {
    }

    public function getDecorated(): AbstractUpdatePaymentMethodRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/mollie/subscription/{subscriptionId}/payment/update',
        name: 'store-api.mollie.subscription.payment.update',
        defaults: ['_loginRequired' => true],
        methods: ['POST']
    )]
    public function start(string $subscriptionId, RequestDataBag $data, SalesChannelContext $context): UpdatePaymentMethodResponse
    {
        $customer = $context->getCustomer();
        if (! $customer instanceof CustomerEntity) {
            throw UpdatePaymentMethodException::notAuthenticated();
        }

        $subscriptionId = strtolower($subscriptionId);

        $subscriptionData = $this->subscriptionDataService->findById($subscriptionId, $context->getContext());
        $subscription = $subscriptionData->getSubscription();
        $orderNumber = (string) $subscriptionData->getOrder()->getOrderNumber();

        $this->assertOwnership($subscription, $customer);
        $this->assertSubscriptionsEnabled($subscription);
        $this->assertPaymentUpdateAllowed($subscription);

        $redirectUrl = (string) $data->get('redirectUrl', '');

        $payment = $this->action->start($subscription, $orderNumber, $redirectUrl, $context->getContext());

        return new UpdatePaymentMethodResponse($subscription->getId(), $payment->getCheckoutUrl());
    }

    #[Route(
        path: '/store-api/mollie/subscription/{subscriptionId}/payment/update/finish',
        name: 'store-api.mollie.subscription.payment.update.finish',
        defaults: ['_loginRequired' => true],
        methods: ['POST']
    )]
    public function confirm(string $subscriptionId, SalesChannelContext $context): UpdatePaymentMethodConfirmedResponse
    {
        $customer = $context->getCustomer();
        if (! $customer instanceof CustomerEntity) {
            throw UpdatePaymentMethodException::notAuthenticated();
        }

        $subscriptionId = strtolower($subscriptionId);

        $subscriptionData = $this->subscriptionDataService->findById($subscriptionId, $context->getContext());
        $subscription = $subscriptionData->getSubscription();
        $orderNumber = (string) $subscriptionData->getOrder()->getOrderNumber();

        $this->assertOwnership($subscription, $customer);
        $this->assertSubscriptionActive($subscription);

        $this->action->confirm($subscription, $orderNumber, $context->getContext());

        return new UpdatePaymentMethodConfirmedResponse($subscription->getId());
    }

    private function assertOwnership(SubscriptionEntity $subscription, CustomerEntity $customer): void
    {
        if ($subscription->getCustomerId() !== $customer->getId()) {
            throw UpdatePaymentMethodException::notOwner($subscription->getId());
        }
    }

    private function assertSubscriptionsEnabled(SubscriptionEntity $subscription): void
    {
        $settings = $this->settingsService->getSubscriptionSettings($subscription->getSalesChannelId());
        if (! $settings->isEnabled()) {
            throw UpdatePaymentMethodException::subscriptionsDisabled($subscription->getId(), $subscription->getSalesChannelId());
        }
    }

    private function assertPaymentUpdateAllowed(SubscriptionEntity $subscription): void
    {
        if (! $subscription->isUpdatePaymentAllowed()) {
            throw UpdatePaymentMethodException::paymentUpdateNotAllowed($subscription->getId());
        }
    }

    private function assertSubscriptionActive(SubscriptionEntity $subscription): void
    {
        $status = $subscription->getStatus();
        if ($status !== SubscriptionStatus::ACTIVE->value && $status !== SubscriptionStatus::RESUMED->value) {
            throw UpdatePaymentMethodException::subscriptionNotActive($subscription->getId(), $status);
        }
    }
}
