<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\Subscription;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionCollection;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionStatus;
use Kiener\MolliePayments\Components\Subscription\SubscriptionManager;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Traits\Api\ApiTrait;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class SubscriptionControllerBase extends AbstractController
{
    use ApiTrait;

    /**
     * @var SubscriptionManager
     */
    private $subscriptionManager;

    private MollieApiFactory $mollieApiFactory;

    /**
     * @var EntityRepository<CustomerCollection<CustomerEntity>>
     */
    private EntityRepository $customerRepository;
    private SettingsService $settingsService;
    private LoggerInterface $logger;

    /**
     * @var EntityRepository<SubscriptionCollection<SubscriptionEntity>>
     */
    private EntityRepository $subscriptionRepository;

    /**
     * @param EntityRepository<CustomerCollection<CustomerEntity>> $customerRepository
     * @param EntityRepository<SubscriptionCollection<SubscriptionEntity>> $subscriptionRepository
     */
    public function __construct(
        SubscriptionManager $subscriptionManager,
        MollieApiFactory $mollieApiFactory,
        EntityRepository $customerRepository,
        EntityRepository $subscriptionRepository,
        SettingsService $settingsService,
        LoggerInterface $logger
    ) {
        $this->subscriptionManager = $subscriptionManager;
        $this->mollieApiFactory = $mollieApiFactory;
        $this->customerRepository = $customerRepository;
        $this->settingsService = $settingsService;
        $this->logger = $logger;
        $this->subscriptionRepository = $subscriptionRepository;
    }

    public function cancel(RequestDataBag $data, Context $context): JsonResponse
    {
        try {
            $this->subscriptionManager->cancelSubscription(
                $data->get('id'),
                $context
            );

            return new JsonResponse(['success' => true]);
        } catch (\Throwable $ex) {
            return $this->buildErrorResponse($ex->getMessage());
        }
    }

    public function cancelLegacy(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->cancel($data, $context);
    }

    public function pause(RequestDataBag $data, Context $context): JsonResponse
    {
        try {
            $this->subscriptionManager->pauseSubscription(
                $data->get('id'),
                $context
            );

            return new JsonResponse(['success' => true]);
        } catch (\Throwable $ex) {
            return $this->buildErrorResponse($ex->getMessage());
        }
    }

    public function pauseLegacy(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->pause($data, $context);
    }

    public function resume(RequestDataBag $data, Context $context): JsonResponse
    {
        try {
            $this->subscriptionManager->resumeSubscription(
                $data->get('id'),
                new \DateTime(),
                $context
            );

            return new JsonResponse(['success' => true]);
        } catch (\Throwable $ex) {
            return $this->buildErrorResponse($ex->getMessage());
        }
    }

    public function resumeLegacy(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->resume($data, $context);
    }

    public function skip(RequestDataBag $data, Context $context): JsonResponse
    {
        try {
            $this->subscriptionManager->skipSubscription(
                $data->get('id'),
                1,
                $context
            );

            return new JsonResponse(['success' => true]);
        } catch (\Throwable $ex) {
            return $this->buildErrorResponse($ex->getMessage());
        }
    }

    public function skipLegacy(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->skip($data, $context);
    }

    public function listUserMollieSubscriptions(string $customerId, Context $context): JsonResponse
    {
        try {
            $response = [
                'success' => true,
                'subscriptions' => []
            ];

            $criteria = new Criteria([strtolower($customerId)]);
            $customer = $this->customerRepository->search($criteria, $context)->first();
            if (! $customer instanceof CustomerEntity) {
                throw new \Exception('Customer with ID ' . $customerId . ' not found in Shopware');
            }
            $salesChannelId = $customer->getSalesChannelId();
            $settings = $this->settingsService->getSettings($salesChannelId);

            $mollieCustomerIds = $this->getCustomerIds($customer, $settings->isTestMode());
            if (count($mollieCustomerIds) === 0) {
                $this->logger->warning('Could not load subscriptions from Mollie, the customer is not connected to Mollies customer, please check the custom fields', [
                    'customerId' => $customerId,
                    'testMode' => $settings->isTestMode()
                ]);

                return new JsonResponse($response);
            }

            $client = $this->mollieApiFactory->getClient($salesChannelId);
            $subscriptions = [];
            foreach ($mollieCustomerIds as $mollieCustomerId) {
                $apiSubscriptions = $client->subscriptions->listForId($mollieCustomerId);
                $subscriptions = array_merge($subscriptions, $apiSubscriptions->getArrayCopy());
            }

            $response['subscriptions'] = $subscriptions;

            return new JsonResponse($response);
        } catch (\Throwable $ex) {
            return $this->buildErrorResponse($ex->getMessage());
        }
    }

    public function cancelByMollieId(string $mollieCustomerId, string $mollieSubscriptionId, string $salesChannelId, Context $context): JsonResponse
    {
        try {
            $response = [
                'success' => true,
            ];

            $client = $this->mollieApiFactory->getClient($salesChannelId);

            $subscription = $client->subscriptions->getForId($mollieCustomerId, $mollieSubscriptionId);

            try {
                $subscription = $subscription->cancel();
            } catch (\Exception $ex) {
                $mandate = $client->mandates->getForId($mollieCustomerId, $subscription->mandateId);
                $mandate->revoke();
                $subscription->status = 'cancelled';
            }

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('mollieId', $mollieSubscriptionId));
            $result = $this->subscriptionRepository->search($criteria, $context);

            if ($result->count() > 0) {
                $upsertArray = [];
                /** @var SubscriptionEntity $subscriptionEntity */
                foreach ($result as $subscriptionEntity) {
                    $upsertArray[] = [
                        'id' => $subscriptionEntity->getId(),
                        'status' => SubscriptionStatus::CANCELED,
                        'nextPaymentAt' => null,
                        'canceledAt' => new \DateTime(),
                        'historyEntries' => [
                            [
                                'statusFrom' => $subscriptionEntity->getStatus(),
                                'statusTo' => SubscriptionStatus::CANCELED,
                                'comment' => 'canceled',
                                'mollieId' => $mollieSubscriptionId,
                            ],
                        ],
                    ];
                }
                $this->subscriptionRepository->upsert($upsertArray, $context);
            }

            $response['subscription'] = $subscription;

            return new JsonResponse($response);
        } catch (\Throwable $ex) {
            return $this->buildErrorResponse($ex->getMessage());
        }
    }

    /**
     * @return string[]
     */
    private function getCustomerIds(CustomerEntity $customer, bool $testMode = true): array
    {
        $result = [];
        $mode = $testMode ? 'test' : 'live';

        $customFields = $customer->getCustomFields()['mollie_payments']['customer_ids'] ?? [];

        foreach ($customFields as $modes) {
            $customerId = $modes[$mode] ?? null;
            if ($customerId === null) {
                continue;
            }
            $result[] = $customerId;
        }

        return array_unique($result);
    }
}
