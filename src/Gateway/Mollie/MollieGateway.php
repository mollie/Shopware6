<?php

namespace Kiener\MolliePayments\Gateway\Mollie;


use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Profile;


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
     * @param MollieApiFactory $clientFactory
     */
    public function __construct(MollieApiFactory $clientFactory)
    {
        $this->factory = $clientFactory;
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
     * @return string
     * @throws \Mollie\Api\Exceptions\ApiException
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
     * @return string
     * @throws \Mollie\Api\Exceptions\ApiException
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
     * @param string $orderId
     * @return Order
     * @throws \Mollie\Api\Exceptions\ApiException
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

}
