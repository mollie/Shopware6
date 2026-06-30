<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Route;

use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\SubscriptionDataService;
use Mollie\Shopware\Component\Subscription\SubscriptionDataServiceInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['store-api']])]
final class UpdateAddressRoute extends AbstractUpdateAddressRoute
{
    /**
     * @param EntityRepository<SubscriptionCollection<SubscriptionEntity>> $subscriptionRepository
     */
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        #[Autowire(service: SubscriptionDataService::class)]
        private readonly SubscriptionDataServiceInterface $subscriptionDataService,
        #[Autowire(service: 'mollie_subscription.repository')]
        private readonly EntityRepository $subscriptionRepository,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
    }

    public function getDecorated(): AbstractUpdateAddressRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/mollie/subscription/{subscriptionId}/billing/update',
        name: 'store-api.mollie.subscription.billing.update',
        defaults: ['_loginRequired' => true],
        methods: ['POST']
    )]
    public function updateBilling(string $subscriptionId, RequestDataBag $data, SalesChannelContext $context): UpdateAddressResponse
    {
        return $this->update(self::TYPE_BILLING, $subscriptionId, UpdateAddressData::fromRequestData($data), $context);
    }

    #[Route(
        path: '/store-api/mollie/subscription/{subscriptionId}/shipping/update',
        name: 'store-api.mollie.subscription.shipping.update',
        defaults: ['_loginRequired' => true],
        methods: ['POST']
    )]
    public function updateShipping(string $subscriptionId, RequestDataBag $data, SalesChannelContext $context): UpdateAddressResponse
    {
        return $this->update(self::TYPE_SHIPPING, $subscriptionId, UpdateAddressData::fromRequestData($data), $context);
    }

    private function update(string $type, string $subscriptionId, UpdateAddressData $data, SalesChannelContext $context): UpdateAddressResponse
    {
        $customer = $context->getCustomer();
        if (! $customer instanceof CustomerEntity) {
            throw UpdateAddressException::notAuthenticated();
        }

        $subscriptionId = strtolower($subscriptionId);

        $this->assertRequiredFields($data);

        $subscriptionData = $this->subscriptionDataService->findById($subscriptionId, $context->getContext());
        $subscription = $subscriptionData->getSubscription();

        $this->assertOwnership($subscription, $customer);

        $salesChannelId = $subscription->getSalesChannelId();
        $settings = $this->settingsService->getSubscriptionSettings($salesChannelId);

        if (! $settings->isEnabled()) {
            throw UpdateAddressException::subscriptionsDisabled($subscriptionId, $salesChannelId);
        }
        if (! $settings->isAllowEditAddress()) {
            throw UpdateAddressException::addressEditingDisabled($subscriptionId, $salesChannelId);
        }

        $existing = $type === self::TYPE_BILLING
            ? $subscription->getBillingAddress()
            : $subscription->getShippingAddress();
        $addressId = $existing instanceof SubscriptionAddressEntity ? $existing->getId() : Uuid::randomHex();

        // The address form does not allow editing the country, so the country is
        // preserved from the existing address instead of being taken from the request.
        $countryId = $existing instanceof SubscriptionAddressEntity ? $existing->getCountryId() : $data->countryId;
        $countryStateId = $existing instanceof SubscriptionAddressEntity ? $existing->getCountryStateId() : $data->countryStateId;

        $associationKey = $type === self::TYPE_BILLING ? 'billingAddress' : 'shippingAddress';
        $historyComment = $type === self::TYPE_BILLING
            ? 'billing address updated'
            : 'shipping address updated';

        $this->subscriptionRepository->upsert([[
            'id' => $subscription->getId(),
            $associationKey => $this->buildAddressPayload($addressId, $subscription->getId(), $data, $countryId, $countryStateId),
            'historyEntries' => [[
                'statusFrom' => '',
                'statusTo' => '',
                'comment' => $historyComment,
                'mollieId' => $subscription->getMollieId(),
            ]],
        ]], $context->getContext());

        $this->logger->info('Subscription address updated', [
            'subscriptionId' => $subscription->getId(),
            'salesChannelId' => $salesChannelId,
            'type' => $type,
            'addressId' => $addressId,
        ]);

        return new UpdateAddressResponse($subscription->getId(), $addressId, $type);
    }

    private function assertRequiredFields(UpdateAddressData $data): void
    {
        if ($data->salutationId === '') {
            throw UpdateAddressException::requiredFieldMissing('salutationId');
        }
        if ($data->firstName === '') {
            throw UpdateAddressException::requiredFieldMissing('firstName');
        }
        if ($data->lastName === '') {
            throw UpdateAddressException::requiredFieldMissing('lastName');
        }
        if ($data->street === '') {
            throw UpdateAddressException::requiredFieldMissing('street');
        }
        if ($data->zipcode === '') {
            throw UpdateAddressException::requiredFieldMissing('zipcode');
        }
        if ($data->city === '') {
            throw UpdateAddressException::requiredFieldMissing('city');
        }
    }

    private function assertOwnership(SubscriptionEntity $subscription, CustomerEntity $customer): void
    {
        if ($subscription->getCustomerId() !== $customer->getId()) {
            throw UpdateAddressException::notOwner($subscription->getId());
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function buildAddressPayload(string $addressId, string $subscriptionId, UpdateAddressData $data, string $countryId, ?string $countryStateId): array
    {
        return [
            'id' => $addressId,
            'subscriptionId' => $subscriptionId,
            'salutationId' => $data->salutationId,
            'title' => $data->title,
            'firstName' => $data->firstName,
            'lastName' => $data->lastName,
            'company' => $data->company,
            'department' => $data->department,
            'phoneNumber' => $data->phoneNumber,
            'street' => $data->street,
            'zipcode' => $data->zipcode,
            'city' => $data->city,
            'countryId' => $countryId,
            'countryStateId' => $countryStateId,
            'additionalAddressLine1' => $data->additionalAddressLine1,
            'additionalAddressLine2' => $data->additionalAddressLine2,
        ];
    }
}
