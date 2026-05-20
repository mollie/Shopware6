<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Exception\CouldNotFetchMollieCustomerMandatesException;
use Kiener\MolliePayments\Exception\CouldNotRevokeMollieCustomerMandateException;
use Kiener\MolliePayments\Service\MollieApi\Mandate as MandateApiService;
use Kiener\MolliePayments\Struct\Mandate\CreditCardDetailStruct;
use Kiener\MolliePayments\Struct\Mandate\MandateCollection;
use Kiener\MolliePayments\Struct\Mandate\MandateStruct;
use Mollie\Api\Resources\Mandate;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class MandateService implements MandateServiceInterface
{
    public const MANDATE_METHOD_CREDIT_CARD = 'creditcard';

    /** @var CustomerServiceInterface */
    private $customerService;

    /** @var MandateApiService */
    private $mandateApiService;

    /**
     * @var EntityRepository<SubscriptionCollection<SubscriptionEntity>>
     */
    private EntityRepository $subscriptionRepository;

    /** @var LoggerInterface */
    private $logger;

    /**
     * @param EntityRepository<SubscriptionCollection<SubscriptionEntity>> $subscriptionRepository
     */
    public function __construct(CustomerServiceInterface $customerService, MandateApiService $mandateApiService, EntityRepository $subscriptionRepository, LoggerInterface $logger)
    {
        $this->customerService = $customerService;
        $this->mandateApiService = $mandateApiService;
        $this->subscriptionRepository = $subscriptionRepository;
        $this->logger = $logger;
    }

    /**
     * @throws CouldNotRevokeMollieCustomerMandateException
     */
    public function revokeMandateByCustomerId(string $customerId, string $mandateId, SalesChannelContext $context): void
    {
        $mollieCustomerId = $this->customerService->getMollieCustomerId($customerId, $context->getSalesChannelId(), $context->getContext());

        $subscriptions = $this->findSubscriptionsByMandateId($customerId, $mandateId, $context->getContext());

        foreach ($subscriptions as $subscription) {
            if ($subscription->isActive() || $subscription->isSkipped()) {
                throw new \Exception('Active subscription found for this mandate');
            }
        }

        $this->mandateApiService->revokeMandateByMollieCustomerId(
            $mollieCustomerId,
            $mandateId,
            $context->getSalesChannelId()
        );
    }

    /**
     * @throws CouldNotFetchMollieCustomerMandatesException
     * @throws \Exception
     */
    public function getCreditCardMandatesByCustomerId(string $customerId, SalesChannelContext $context): MandateCollection
    {
        $mollieCustomerId = $this->customerService->getMollieCustomerId(
            $customerId,
            $context->getSalesChannelId(),
            $context->getContext()
        );

        $mandatesArray = [];

        if (strlen($mollieCustomerId) === 0) {
            return new MandateCollection();
        }

        try {
            $mandates = $this->mandateApiService->getMandatesByMollieCustomerId($mollieCustomerId, $context->getSalesChannelId());
            $mandatesArray = $mandates->getArrayCopy();
        } catch (CouldNotFetchMollieCustomerMandatesException $e) {
            $this->logger->warning('Could not fetch mandates for customer, resetting customer IDs', [
                'customerId' => $customerId,
                'mollieCustomerId' => $mollieCustomerId,
                'exception' => $e->getMessage(),
            ]);
            $customFields = $this->customerService->getCustomerStruct($customerId, $context->getContext());
            $customFields->setCustomerIds([]);
            $this->customerService->saveCustomerCustomFields($customerId, $customFields->toCustomFieldsArray(), $context->getContext());
        }

        return $this->parseCreditCardMandateToStruct($mandatesArray, $customerId, $context->getContext());
    }

    /**
     * This function will parse the mandate collection to struct, and only get the creditcard method
     *
     * @param array<Mandate> $mandates
     *
     * @throws \Exception
     */
    private function parseCreditCardMandateToStruct(array $mandates, string $customerId, Context $context): MandateCollection
    {
        $mandateCollection = new MandateCollection();
        foreach ($mandates as $mandate) {
            // only get the mandate has method type creditcard
            if ($mandate->method !== self::MANDATE_METHOD_CREDIT_CARD) {
                continue;
            }

            $details = new CreditCardDetailStruct();
            if ($mandate->details instanceof \stdClass && $mandateDetail = json_encode($mandate->details)) {
                $mandateDetail = json_decode($mandateDetail, true);
                if (is_array($mandateDetail)) {
                    $details = $details->assign($mandateDetail);
                }
            }

            $mandateStruct = new MandateStruct();
            $mandateStruct->setResource($mandate->resource);
            $mandateStruct->setId($mandate->id);
            $mandateStruct->setMethod($mandate->method);
            $mandateStruct->setMode($mandate->mode);
            $mandateStruct->setStatus($mandate->status);
            $mandateStruct->setCustomerId($mandate->customerId);
            $mandateStruct->setMandateReference($mandate->mandateReference);
            $mandateStruct->setSignatureDate($mandate->signatureDate);
            $mandateStruct->setCreatedAt($mandate->createdAt);
            $mandateStruct->setDetails($details);

            // check if this mandate has connected subscriptions
            $beingUsedForSubscription = false;
            foreach ($this->findSubscriptionsByMandateId($customerId, $mandate->id, $context) as $subscription) {
                if ($subscription->isActive() || $subscription->isSkipped()) {
                    $beingUsedForSubscription = true;
                    break;
                }
            }
            $mandateStruct->setBeingUsedForSubscription($beingUsedForSubscription);

            $mandateCollection->add($mandateStruct);
        }

        return $mandateCollection;
    }

    /**
     * @return iterable<SubscriptionEntity>
     */
    private function findSubscriptionsByMandateId(string $customerId, string $mandateId, Context $context): iterable
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $criteria->addFilter(new EqualsFilter('mandateId', $mandateId));

        return $this->subscriptionRepository->search($criteria, $context)->getEntities();
    }
}
