<?php

namespace Kiener\MolliePayments\Gateway\Mollie;

use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Gateway\Mollie\Model\Issuer;
use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Method;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\Profile;
use Mollie\Api\Resources\Subscription;
use Mollie\Api\Types\PaymentMethod;

class MollieGateway implements MollieGatewayInterface
{
    /**
     * @var MollieApiClient
     */
    private $apiClient;

    /**
     * @var MollieApiFactory
     */
    private $factory;


    /**
     * @param MollieApiFactory $factory
     */
    public function __construct(MollieApiFactory $factory)
    {
        $this->factory = $factory;
    }


    /**
     * @param string $salesChannelID
     * @throws \Mollie\Api\Exceptions\IncompatiblePlatform
     */
    public function switchClient(string $salesChannelID): void
    {
        $this->apiClient = $this->factory->getClient($salesChannelID);
    }

    /**
     * @throws \Mollie\Api\Exceptions\ApiException
     * @return string
     */
    public function getProfileId(): string
    {
        $profile = $this->apiClient->profiles->get('me');

        if (!$profile instanceof Profile) {
            return '';
        }

        return (string)$profile->id;
    }

    /**
     * @throws \Mollie\Api\Exceptions\ApiException
     * @return string
     */
    public function getOrganizationId(): string
    {
        $profile = $this->apiClient->profiles->get('me');

        if (!$profile instanceof Profile) {
            return '';
        }

        # the organization is in a full dashboard URL
        # so we grab it, and extract that slug with the organization id
        $orgId = (string)$profile->_links->dashboard->href;

        $parts = explode('/', $orgId);

        foreach ($parts as $part) {
            if (strpos($part, 'org_') === 0) {
                $orgId = $part;
                break;
            }
        }

        return (string)$orgId;
    }

    /**
     * @throws \Mollie\Api\Exceptions\ApiException
     * @return Issuer[]
     */
    public function getIDealIssuers(): array
    {
        $parameters = [
            'include' => 'issuers',
        ];

        /** @var Method $iDeal */
        $iDeal = $this->apiClient->methods->get(PaymentMethod::IDEAL, $parameters);

        $issuers = [];

        /** @var \Mollie\Api\Resources\Issuer $issuer */
        foreach ($iDeal->issuers as $issuer) {
            $issuers[] = new Issuer(
                $issuer->id,
                $issuer->name,
                $issuer->image->size1x,
                $issuer->image->size2x,
                $issuer->image->svg
            );
        }

        return $issuers;
    }

    /**
     * @param string $orderId
     * @throws \Mollie\Api\Exceptions\ApiException
     * @return Order
     */
    public function getOrder(string $orderId): Order
    {
        $order = $this->apiClient->orders->get(
            $orderId,
            [
                'embed' => 'payments',
            ]
        );

        return $order;
    }

    /**
     * @param string $paymentId
     * @throws \Mollie\Api\Exceptions\ApiException
     * @return Payment
     */
    public function getPayment(string $paymentId): Payment
    {
        return $this->apiClient->payments->get($paymentId);
    }

    /**
     * @param array<mixed> $data
     * @throws \Mollie\Api\Exceptions\ApiException
     * @return Payment
     */
    public function createPayment(array $data): Payment
    {
        return $this->apiClient->payments->create($data);
    }

    /**
     * @param string $customerID
     * @param array<mixed> $data
     * @throws \Mollie\Api\Exceptions\ApiException
     * @return Subscription
     */
    public function createSubscription(string $customerID, array $data): Subscription
    {
        return $this->apiClient->subscriptions->createForId($customerID, $data);
    }

    /**
     * @param string $subscriptionId
     * @param string $customerId
     * @throws \Mollie\Api\Exceptions\ApiException
     * @return void
     */
    public function cancelSubscription(string $subscriptionId, string $customerId): void
    {
        $this->apiClient->subscriptions->cancelForId(
            $customerId,
            $subscriptionId
        );
    }

    /**
     * @param string $subscriptionId
     * @param string $customerId
     * @param string $mandateId
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function updateSubscription(string $subscriptionId, string $customerId, string $mandateId): void
    {
        $this->apiClient->subscriptions->update(
            $customerId,
            $subscriptionId,
            [
                'mandateId' => $mandateId,
            ]
        );
    }

    /**
     * @param string $subscriptionId
     * @param string $customerId
     * @throws \Mollie\Api\Exceptions\ApiException
     * @return Subscription
     */
    public function getSubscription(string $subscriptionId, string $customerId): Subscription
    {
        return $this->apiClient->subscriptions->getForId($customerId, $subscriptionId);
    }
}
