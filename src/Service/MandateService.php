<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Components\Subscription\SubscriptionManager;
use Kiener\MolliePayments\Exception\CouldNotFetchMollieCustomerMandatesException;
use Kiener\MolliePayments\Exception\CouldNotRevokeMollieCustomerMandateException;
use Kiener\MolliePayments\Service\MollieApi\Mandate as MandateApiService;
use Kiener\MolliePayments\Struct\Mandate\CreditCardDetailStruct;
use Kiener\MolliePayments\Struct\Mandate\MandateCollection;
use Kiener\MolliePayments\Struct\Mandate\MandateStruct;
use Mollie\Api\Resources\Mandate;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class MandateService implements MandateServiceInterface
{
    public const MANDATE_METHOD_CREDIT_CARD = 'creditcard';

    /** @var CustomerServiceInterface */
    private $customerService;

    /** @var MandateApiService */
    private $mandateApiService;

    /** @var SubscriptionManager */
    private $subscriptionManager;

    public function __construct(CustomerServiceInterface $customerService, MandateApiService $mandateApiService, SubscriptionManager $subscriptionManager)
    {
        $this->customerService = $customerService;
        $this->mandateApiService = $mandateApiService;
        $this->subscriptionManager = $subscriptionManager;
    }

    /**
     * @throws CouldNotRevokeMollieCustomerMandateException
     */
    public function revokeMandateByCustomerId(string $customerId, string $mandateId, SalesChannelContext $context): void
    {
        $mollieCustomerId = $this->customerService->getMollieCustomerId($customerId, $context->getSalesChannelId(), $context->getContext());

        $subscriptions = $this->subscriptionManager->findSubscriptionByMandateId($customerId, $mandateId, $context->getContext());

        foreach ($subscriptions->getElements() as $subscription) {
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
        try {
            $mandates = $this->mandateApiService->getMandatesByMollieCustomerId($mollieCustomerId, $context->getSalesChannelId());
        } catch (CouldNotFetchMollieCustomerMandatesException $e) {
            $customFields = $this->customerService->getCustomerStruct($customerId, $context->getContext());
            $customFields->setCustomerIds([]);
            $this->customerService->saveCustomerCustomFields($customerId, $customFields->toCustomFieldsArray(), $context->getContext());
        }

        return $this->parseCreditCardMandateToStruct($mandates->getArrayCopy(), $customerId, $context->getContext());
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
            $subscriptions = $this->subscriptionManager->findSubscriptionByMandateId($customerId, $mandate->id, $context);
            $beingUsedForSubscription = false;

            foreach ($subscriptions->getElements() as $subscription) {
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
}
