<?php

namespace Kiener\MolliePayments\Service;

use Exception;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionCollection;
use Kiener\MolliePayments\Components\Subscription\SubscriptionManager;
use Kiener\MolliePayments\Exception\CouldNotFetchMollieCustomerException;
use Kiener\MolliePayments\Exception\CouldNotFetchMollieCustomerMandatesException;
use Kiener\MolliePayments\Exception\CustomerCouldNotBeFoundException;
use Kiener\MolliePayments\Service\MollieApi\Mandate as MandateApiService;
use Kiener\MolliePayments\Struct\Mandate\CreditCardDetailStruct;
use Kiener\MolliePayments\Struct\Mandate\MandateCollection;
use Kiener\MolliePayments\Struct\Mandate\MandateStruct;
use Mollie\Api\Resources\Mandate;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class MandateService implements MandateServiceInterface
{
    public const MANDATE_METHOD_CREDIT_CARD = 'creditcard';

    /** @var LoggerInterface */
    private $logger;

    /** @var CustomerServiceInterface */
    private $customerService;

    /** @var MandateApiService */
    private $mandateApiService;

    /** @var SubscriptionManager */
    private $subscriptionManager;

    /**
     * Creates a new instance of the mandate service.
     *
     * @param LoggerInterface $logger
     * @param CustomerServiceInterface $customerService
     * @param MandateApiService $mandateApiService
     * @param SubscriptionManager $subscriptionManager
     */
    public function __construct(
        LoggerInterface $logger,
        CustomerServiceInterface $customerService,
        MandateApiService $mandateApiService,
        SubscriptionManager $subscriptionManager
    ) {
        $this->logger = $logger;
        $this->customerService = $customerService;
        $this->mandateApiService = $mandateApiService;
        $this->subscriptionManager = $subscriptionManager;
    }

    /**
     * Revoking a mandate by mandateId. Before revoking a mandate, all connected subscriptions will be canceled.
     *
     * @param string $customerId
     * @param string $mandateId
     * @param SalesChannelContext $context
     * @throws CustomerCouldNotBeFoundException
     * @throws CouldNotFetchMollieCustomerMandatesException
     * @return void
     */
    public function revokeMandateByCustomerId(string $customerId, string $mandateId, SalesChannelContext $context): void
    {
        try {
            $mollieCustomerId = $this->customerService->getMollieCustomerId($customerId, $context->getSalesChannelId(), $context->getContext());

            $subscriptions = $this->subscriptionManager->findSubscriptionByMandateId($customerId, $mandateId, $context->getContext());
            # cancel all connected subscriptions before revoking the mandate
            if ($subscriptions->count() > 0) {
                # skip revoking the mandate if any subscription is not cancelable
                foreach ($subscriptions->getElements() as $subscription) {
                    $isCancellable = $this->subscriptionManager->isCancelable($subscription, $context->getContext());
                    if ($isCancellable) {
                        continue;
                    }

                    throw new Exception(sprintf(
                        'Subscription ID %s is not possible to cancel. Mandate ID %s can\'t be removed.',
                        $subscription->getId(),
                        $mandateId
                    ));
                }

                # cancel all connected subscription if they are cancelable
                foreach ($subscriptions->getElements() as $subscription) {
                    $this->subscriptionManager->cancelSubscription($subscription->getId(), $context->getContext());
                }
            }

            $this->logger->debug('Revoking a mandate of the Mollie customer', [
                'customerId' => $customerId,
                'mollieCustomerId' => $mollieCustomerId,
                'mandateId' => $mandateId,
            ]);

            $this->mandateApiService->revokeMandateByMollieCustomerId($mollieCustomerId, $mandateId, $context->getSalesChannelId());
        } catch (Exception $exception) {
            $this->logger->error('Error while revoking a mandate', [
                'error' => $exception->getMessage(),
                'customerId' => $customerId,
                'mandateId' => $mandateId,
            ]);

            throw $exception;
        }
    }

    /**
     * @param string $customerId
     * @param SalesChannelContext $context
     * @throws CouldNotFetchMollieCustomerMandatesException
     * @throws Exception
     * @return MandateCollection
     */
    public function getCreditCardMandatesByCustomerId(string $customerId, SalesChannelContext $context): MandateCollection
    {
        $mollieCustomerId = $this->customerService->getMollieCustomerId(
            $customerId,
            $context->getSalesChannelId(),
            $context->getContext()
        );

        $mandates = $this->mandateApiService->getMandatesByMollieCustomerId($mollieCustomerId, $context->getSalesChannelId());

        return $this->parseCreditCardMandateToStruct($mandates->getArrayCopy(), $customerId, $context->getContext());
    }

    /**
     * This function will parse the mandate collection to struct, and only get the creditcard method
     *
     * @param array<Mandate> $mandates
     * @throws Exception
     * @return MandateCollection
     */
    private function parseCreditCardMandateToStruct(array $mandates, string $customerId, Context $context): MandateCollection
    {
        $mandateCollection = new MandateCollection();
        foreach ($mandates as $mandate) {
            // only get the mandate has method type creditcard
            if (!$mandate instanceof Mandate || $mandate->method !== self::MANDATE_METHOD_CREDIT_CARD) {
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

            # check if this mandate has connected subscriptions
            $subscriptions = $this->subscriptionManager->findSubscriptionByMandateId($customerId, $mandate->id, $context);
            if ($subscriptions->count() > 0) {
                $mandateStruct->setBeingUsedForSubscription(true);
            }

            $mandateCollection->add($mandateStruct);
        }

        return $mandateCollection;
    }
}
