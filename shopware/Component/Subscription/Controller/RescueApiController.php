<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Controller;

use Kiener\MolliePayments\Factory\MollieApiFactory;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionStatus;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin "last resort" tooling for subscriptions.
 *
 * These endpoints exist for the admin UI on the customer detail page so a
 * support agent can inspect every subscription that lives in Mollie for a
 * given Shopware customer (not just the ones present in the local DB) and
 * force-cancel one when the regular workflow has failed - e.g. after repeated
 * customer complaints about charges that the normal pause/cancel actions
 * could not stop. This is *not* part of the standard customer flow; it is
 * the manual override that guarantees no further direct debits happen.
 *
 * The cancel endpoint also revokes the mandate as a fallback when the
 * subscription cancel call against Mollie throws, so that even if the
 * subscription record is in a broken state Mollie can no longer charge the
 * customer with that mandate.
 */
#[AsController]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
final class RescueApiController extends AbstractController
{
    /**
     * @param EntityRepository<CustomerCollection<CustomerEntity>> $customerRepository
     * @param EntityRepository<SubscriptionCollection<SubscriptionEntity>> $subscriptionRepository
     */
    public function __construct(
        private readonly MollieApiFactory $mollieApiFactory,
        #[Autowire(service: 'customer.repository')]
        private readonly EntityRepository $customerRepository,
        #[Autowire(service: 'mollie_subscription.repository')]
        private readonly EntityRepository $subscriptionRepository,
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route(
        path: '/api/_action/mollie/subscriptions/{customerId}',
        name: 'api.action.mollie.subscription.list',
        methods: ['GET']
    )]
    public function listUserMollieSubscriptions(string $customerId, Context $context): JsonResponse
    {
        try {
            $criteria = new Criteria([strtolower($customerId)]);
            $customer = $this->customerRepository->search($criteria, $context)->first();
            if (! $customer instanceof CustomerEntity) {
                throw new \RuntimeException('Customer with ID ' . $customerId . ' not found in Shopware');
            }

            $salesChannelId = $customer->getSalesChannelId();
            $apiSettings = $this->settingsService->getApiSettings($salesChannelId);

            $mollieCustomerIds = $this->getCustomerIds($customer, $apiSettings->isTestMode());
            if (count($mollieCustomerIds) === 0) {
                $this->logger->warning('Could not load subscriptions from Mollie, the customer is not connected to a Mollie customer, please check the custom fields', [
                    'customerId' => $customerId,
                    'testMode' => $apiSettings->isTestMode(),
                ]);

                return new JsonResponse(['success' => true, 'subscriptions' => []]);
            }

            $client = $this->mollieApiFactory->getClient($salesChannelId);
            $subscriptions = [];
            foreach ($mollieCustomerIds as $mollieCustomerId) {
                $apiSubscriptions = $client->subscriptions->listForId($mollieCustomerId);
                $subscriptions = array_merge($subscriptions, $apiSubscriptions->getArrayCopy());
            }

            return new JsonResponse(['success' => true, 'subscriptions' => $subscriptions]);
        } catch (\Throwable $exception) {
            return $this->buildErrorResponse($exception->getMessage());
        }
    }

    #[Route(
        path: '/api/_action/mollie/subscriptions/cancel/{mollieCustomerId}/{mollieSubscriptionId}/{mandateId}/{salesChannelId}',
        name: 'api.action.mollie.subscription.cancel_by_customer',
        methods: ['GET']
    )]
    public function cancelByMollieId(
        string $mollieCustomerId,
        string $mollieSubscriptionId,
        string $mandateId,
        string $salesChannelId,
        Context $context
    ): JsonResponse {
        try {
            $client = $this->mollieApiFactory->getClient($salesChannelId);

            try {
                $subscription = $client->subscriptions->getForId($mollieCustomerId, $mollieSubscriptionId);
                $subscription = $subscription->cancel();
            } catch (\Throwable $cancelFailure) {
                // Subscription cancel itself blew up - revoke the mandate so Mollie can no
                // longer charge the customer with it. Pretend the subscription is canceled
                // so the admin UI reflects that the customer is safe from further charges.
                $this->logger->warning('Subscription cancel via Mollie API failed, revoking mandate as fallback', [
                    'mollieCustomerId' => $mollieCustomerId,
                    'mollieSubscriptionId' => $mollieSubscriptionId,
                    'mandateId' => $mandateId,
                    'message' => $cancelFailure->getMessage(),
                ]);
                $mandate = $client->mandates->getForId($mollieCustomerId, $mandateId);
                $mandate->revoke();
                $subscription = [
                    'id' => $mollieSubscriptionId,
                    'status' => SubscriptionStatus::CANCELED,
                ];
            }

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('mollieId', $mollieSubscriptionId));
            $localSubscriptions = $this->subscriptionRepository->search($criteria, $context);

            if ($localSubscriptions->count() > 0) {
                $upsert = [];
                /** @var SubscriptionEntity $localSubscription */
                foreach ($localSubscriptions as $localSubscription) {
                    $upsert[] = [
                        'id' => $localSubscription->getId(),
                        'status' => SubscriptionStatus::CANCELED,
                        'nextPaymentAt' => null,
                        'canceledAt' => new \DateTimeImmutable(),
                        'historyEntries' => [
                            [
                                'statusFrom' => $localSubscription->getStatus(),
                                'statusTo' => SubscriptionStatus::CANCELED,
                                'comment' => 'canceled',
                                'mollieId' => $mollieSubscriptionId,
                            ],
                        ],
                    ];
                }
                $this->subscriptionRepository->upsert($upsert, $context);
            }

            return new JsonResponse(['success' => true, 'subscription' => $subscription]);
        } catch (\Throwable $exception) {
            return $this->buildErrorResponse($exception->getMessage());
        }
    }

    private function buildErrorResponse(string $error): JsonResponse
    {
        // The admin axios client expects an `errors` array - a top-level error string
        // would render as "nothing found at index 0" in the notification UI.
        return new JsonResponse([
            'success' => false,
            'errors' => [$error],
        ], 500);
    }

    /**
     * @return list<string>
     */
    private function getCustomerIds(CustomerEntity $customer, bool $testMode): array
    {
        $mode = $testMode ? 'test' : 'live';
        $customFields = $customer->getCustomFields()['mollie_payments']['customer_ids'] ?? [];

        $ids = [];
        foreach ($customFields as $modes) {
            $customerId = $modes[$mode] ?? null;
            if ($customerId === null) {
                continue;
            }
            $ids[] = $customerId;
        }

        return array_values(array_unique($ids));
    }
}
