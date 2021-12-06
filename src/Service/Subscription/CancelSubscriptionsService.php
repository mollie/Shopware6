<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Subscription;

use Kiener\MolliePayments\Factory\MollieApiFactory;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Kiener\MolliePayments\Service\LoggerService;
use Monolog\Logger;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class CancelSubscriptionsService
{
    public const SUCCESS = 'success';

    /**
     * @var EntityRepositoryInterface
     */
    private $mollieSubscriptionsRepository;

    /**
     * @var MollieApiFactory
     */
    private $apiFactory;

    /**
     * @var LoggerService
     */
    private $loggerService;

    /**
     * @param EntityRepositoryInterface $mollieSubscriptionsRepository
     * @param MollieApiFactory $apiFactory
     * @param LoggerService $loggerService
     */
    public function __construct(
        EntityRepositoryInterface $mollieSubscriptionsRepository,
        MollieApiFactory $apiFactory,
        LoggerService $loggerService
    ) {
        $this->mollieSubscriptionsRepository = $mollieSubscriptionsRepository;
        $this->apiFactory = $apiFactory;
        $this->loggerService = $loggerService;
    }

    /**
     * @param string $subscriptionId
     * @param string $mollieCustomerId
     * @param string $salesChannelId
     * @return bool
     * @throws IncompatiblePlatform
     */
    public function cancelSubscriptions(string $subscriptionId, string $mollieCustomerId, string $salesChannelId)
    {
        $mollieApi = $this->apiFactory->getClient($salesChannelId);
        try {
            $mollieApi->subscriptions->cancelForId($mollieCustomerId, $subscriptionId);
        } catch (\Exception $exception) {
            $this->loggerService->addEntry(
                $exception->getMessage(),
                Context::createDefaultContext(),
                $exception,
                null,
                Logger::ERROR
            );

            return false;
        } finally {
            $this->cancelSubscriptionReference($mollieCustomerId, $subscriptionId);
        }
        return true;
    }

    /**
     * @param string $customerId
     * @param string $subscriptionId
     */
    private function cancelSubscriptionReference(string $customerId, string $subscriptionId)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mollieCustomerId', $customerId));
        $criteria->addFilter(new EqualsFilter('subscriptionId', $subscriptionId));

        $subscription = $this->mollieSubscriptionsRepository
            ->search($criteria, Context::createDefaultContext())
            ->first();

        $this->mollieSubscriptionsRepository->upsert([[
            'id' => $subscription->getId(),
            'status' => 'canceled'
        ]], Context::createDefaultContext());
    }
}