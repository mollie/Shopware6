<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Gateway\Mollie;

use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\Profile;
use Mollie\Api\Resources\Subscription;
use Mollie\Api\Resources\Terminal;

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

    public function __construct(MollieApiFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * @throws \Mollie\Api\Exceptions\IncompatiblePlatform
     */
    public function switchClient(string $salesChannelID): void
    {
        $this->apiClient = $this->factory->getClient($salesChannelID);
    }

    /**
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function getProfileId(): string
    {
        /** @var ?Profile $profile */
        $profile = $this->apiClient->profiles->get('me');

        if (! $profile instanceof Profile) {
            return '';
        }

        return (string) $profile->id;
    }

    /**
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function getOrganizationId(): string
    {
        /** @var ?Profile $profile */
        $profile = $this->apiClient->profiles->get('me');

        if (! $profile instanceof Profile) {
            return '';
        }

        // the organization is in a full dashboard URL
        // so we grab it, and extract that slug with the organization id
        $orgId = (string) $profile->_links->dashboard->href;

        $parts = explode('/', $orgId);

        foreach ($parts as $part) {
            if (strpos($part, 'org_') === 0) {
                $orgId = $part;
                break;
            }
        }

        return (string) $orgId;
    }

    /**
     * @throws \Mollie\Api\Exceptions\ApiException
     *
     * @return array|Terminal[]
     */
    public function getPosTerminals(): array
    {
        $terminals = $this->apiClient->terminals->page();

        $list = [];

        /** @var Terminal $terminal */
        foreach ($terminals as $terminal) {
            $list[] = $terminal;
        }

        return $list;
    }

    /**
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function getOrder(string $orderId): Order
    {
        return $this->apiClient->orders->get(
            $orderId,
            [
                'embed' => 'payments',
            ]
        );
    }

    /**
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function getPayment(string $paymentId): Payment
    {
        return $this->apiClient->payments->get($paymentId);
    }

    /**
     * @param array<mixed> $data
     *
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function createPayment(array $data): Payment
    {
        return $this->apiClient->payments->create($data);
    }

    /**
     * @param array<mixed> $data
     *
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function createSubscription(string $customerID, array $data): Subscription
    {
        return $this->apiClient->subscriptions->createForId($customerID, $data);
    }

    /**
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function cancelSubscription(string $subscriptionId, string $customerId): void
    {
        $this->apiClient->subscriptions->cancelForId(
            $customerId,
            $subscriptionId
        );
    }

    /**
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
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function getSubscription(string $subscriptionId, string $customerId): Subscription
    {
        return $this->apiClient->subscriptions->getForId($customerId, $subscriptionId);
    }
}
