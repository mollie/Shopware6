<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\StoreApi\Subscription;

use Kiener\MolliePayments\Components\Subscription\DAL\Repository\SubscriptionRepository;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionCollection;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Components\Subscription\SubscriptionManager;
use Kiener\MolliePayments\Controller\StoreApi\Subscription\Response\SubscriptionCancelResponse;
use Kiener\MolliePayments\Controller\StoreApi\Subscription\Response\SubscriptionPauseResponse;
use Kiener\MolliePayments\Controller\StoreApi\Subscription\Response\SubscriptionPaymentUpdateResponse;
use Kiener\MolliePayments\Controller\StoreApi\Subscription\Response\SubscriptionResumeResponse;
use Kiener\MolliePayments\Controller\StoreApi\Subscription\Response\SubscriptionSkipResponse;
use Kiener\MolliePayments\Controller\StoreApi\Subscription\Response\SubscriptionsListResponse;
use Kiener\MolliePayments\Controller\StoreApi\Subscription\Response\SubscriptionUpdateBillingResponse;
use Kiener\MolliePayments\Controller\StoreApi\Subscription\Response\SubscriptionUpdateShippingResponse;
use Kiener\MolliePayments\Exception\CustomerCouldNotBeFoundException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class SubscriptionControllerBase
{
    /**
     * @var SubscriptionManager
     */
    private $subscriptionManager;

    /**
     * @var SubscriptionRepository
     */
    private $repoSubscriptions;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(SubscriptionManager $subscriptionManager, SubscriptionRepository $repoSubscriptions, LoggerInterface $logger)
    {
        $this->subscriptionManager = $subscriptionManager;
        $this->repoSubscriptions = $repoSubscriptions;
        $this->logger = $logger;
    }

    /**
     * @throws \Throwable
     */
    public function getSubscriptions(SalesChannelContext $context): StoreApiResponse
    {
        $this->validateRoute($context);

        /** @var CustomerEntity $customer */
        $customer = $context->getCustomer();

        $result = $this->repoSubscriptions->findByCustomer(
            $customer->getId(),
            false,
            $context->getContext()
        );

        /** @var SubscriptionEntity[] $subscriptions */
        $subscriptions = $result->getElements();

        $collection = new SubscriptionCollection($subscriptions);
        $flatList = $collection->getFlatList();

        return new SubscriptionsListResponse($flatList);
    }

    /**
     * @throws \Throwable
     */
    public function updateBilling(string $subscriptionId, RequestDataBag $data, SalesChannelContext $context): StoreApiResponse
    {
        try {
            $this->validateRoute($context);

            // make sure its lower case
            // this is better for handling and testing (it only works lower case
            $subscriptionId = strtolower($subscriptionId);

            $salutationId = strtolower($data->get('salutationId', ''));
            $title = $data->get('title', '');
            $firstname = $data->get('firstName', '');
            $lastname = $data->get('lastName', '');
            $company = $data->get('company', '');
            $department = $data->get('department', '');
            $additional1 = $data->get('additionalField1', '');
            $additional2 = $data->get('additionalField2', '');
            $phone = $data->get('phoneNumber', '');
            $street = $data->get('street', '');
            $zipcode = $data->get('zipcode', '');
            $city = $data->get('city', '');
            $countryStateId = ''; // currently not supported

            $this->subscriptionManager->updateBillingAddress(
                $subscriptionId,
                $salutationId,
                $title,
                $firstname,
                $lastname,
                $company,
                $department,
                $additional1,
                $additional2,
                $phone,
                $street,
                $zipcode,
                $city,
                $countryStateId,
                $context->getContext()
            );

            return new SubscriptionUpdateBillingResponse();
        } catch (\Throwable $ex) {
            $this->logger->error('Error when updating billing of subscription ' . $subscriptionId . ': ' . $ex->getMessage());
            throw $ex;
        }
    }

    /**
     * @throws \Throwable
     */
    public function updateShipping(string $subscriptionId, RequestDataBag $data, SalesChannelContext $context): StoreApiResponse
    {
        try {
            $this->validateRoute($context);

            // make sure its lower case
            // this is better for handling and testing (it only works lower case
            $subscriptionId = strtolower($subscriptionId);

            $salutationId = strtolower($data->get('salutationId', ''));
            $title = $data->get('title', '');
            $firstname = $data->get('firstName', '');
            $lastname = $data->get('lastName', '');
            $company = $data->get('company', '');
            $department = $data->get('department', '');
            $additional1 = $data->get('additionalField1', '');
            $additional2 = $data->get('additionalField2', '');
            $phone = $data->get('phoneNumber', '');
            $street = $data->get('street', '');
            $zipcode = $data->get('zipcode', '');
            $city = $data->get('city', '');
            $countryStateId = ''; // currently not supported

            $this->subscriptionManager->updateShippingAddress(
                $subscriptionId,
                $salutationId,
                $title,
                $firstname,
                $lastname,
                $company,
                $department,
                $additional1,
                $additional2,
                $phone,
                $street,
                $zipcode,
                $city,
                $countryStateId,
                $context->getContext()
            );

            return new SubscriptionUpdateShippingResponse();
        } catch (\Throwable $ex) {
            $this->logger->error('Error when updating shipping of subscription ' . $subscriptionId . ': ' . $ex->getMessage());
            throw $ex;
        }
    }

    /**
     * @throws \Throwable
     * @throws CustomerCouldNotBeFoundException
     */
    public function updatePayment(string $subscriptionId, RequestDataBag $data, SalesChannelContext $context): StoreApiResponse
    {
        try {
            $this->validateRoute($context);

            // make sure its lower case
            // this is better for handling and testing (it only works lower case
            $subscriptionId = strtolower($subscriptionId);

            $redirectUrl = $data->get('redirectUrl', '');

            $checkoutUrl = $this->subscriptionManager->updatePaymentMethodStart($subscriptionId, $redirectUrl, $context->getContext());

            return new SubscriptionPaymentUpdateResponse($checkoutUrl);
        } catch (\Throwable $ex) {
            $this->logger->error('Error when updating payment method of subscription ' . $subscriptionId . ': ' . $ex->getMessage());
            throw $ex;
        }
    }

    /**
     * @throws \Throwable
     */
    public function pause(string $subscriptionId, SalesChannelContext $context): StoreApiResponse
    {
        try {
            $this->validateRoute($context);

            // make sure its lower case
            // this is better for handling and testing (it only works lower case
            $subscriptionId = strtolower($subscriptionId);

            $this->subscriptionManager->pauseSubscription($subscriptionId, $context->getContext());

            return new SubscriptionPauseResponse();
        } catch (\Throwable $ex) {
            $this->logger->error('Error when pausing subscription ' . $subscriptionId . ': ' . $ex->getMessage());
            throw $ex;
        }
    }

    /**
     * @throws \Throwable
     */
    public function resume(string $subscriptionId, SalesChannelContext $context): StoreApiResponse
    {
        try {
            $this->validateRoute($context);

            // make sure its lower case
            // this is better for handling and testing (it only works lower case
            $subscriptionId = strtolower($subscriptionId);

            $this->subscriptionManager->resumeSubscription($subscriptionId, $context->getContext());

            return new SubscriptionResumeResponse();
        } catch (\Throwable $ex) {
            $this->logger->error('Error when resuming subscription ' . $subscriptionId . ': ' . $ex->getMessage());
            throw $ex;
        }
    }

    /**
     * @throws \Throwable
     */
    public function skip(string $subscriptionId, SalesChannelContext $context): StoreApiResponse
    {
        try {
            $this->validateRoute($context);

            // make sure its lower case
            // this is better for handling and testing (it only works lower case
            $subscriptionId = strtolower($subscriptionId);

            $this->subscriptionManager->skipSubscription($subscriptionId, 1, $context->getContext());

            return new SubscriptionSkipResponse();
        } catch (\Throwable $ex) {
            $this->logger->error('Error when skipping subscription ' . $subscriptionId . ': ' . $ex->getMessage());
            throw $ex;
        }
    }

    /**
     * @throws \Throwable
     */
    public function cancel(string $subscriptionId, SalesChannelContext $context): StoreApiResponse
    {
        try {
            $this->validateRoute($context);

            $this->subscriptionManager->cancelSubscription($subscriptionId, $context->getContext());

            return new SubscriptionCancelResponse();
        } catch (\Throwable $ex) {
            $this->logger->error('Error when canceling subscription ' . $subscriptionId . ': ' . $ex->getMessage());
            throw $ex;
        }
    }

    private function validateRoute(SalesChannelContext $context): void
    {
        $customer = $context->getCustomer();

        if (! $customer instanceof CustomerEntity) {
            throw new UnauthorizedHttpException('Unauthorized request! No customer is signed in!');
        }
    }
}
