<?php

namespace Kiener\MolliePayments\Service\Payment\Provider;

use Exception;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\Logger\MollieLogger;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Method;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class ActivePaymentMethodsProvider implements ActivePaymentMethodsProviderInterface
{
    /** @var MollieApiFactory */
    private $mollieApiFactory;

    /** @var MollieLogger */
    private $logger;

    /**
     * Creates a new instance of this class.
     *
     * @param MollieApiFactory $mollieApiFactory
     * @param MollieLogger $logger
     */
    public function __construct(MollieApiFactory $mollieApiFactory, MollieLogger $logger)
    {
        $this->mollieApiFactory = $mollieApiFactory;
        $this->logger = $logger;
    }

    /**
     * Returns an array of active payment methods.
     *
     * @param array<array|scalar> $parameters
     * @param array<SalesChannelEntity> $salesChannels
     * @return array<Method>
     */
    public function getActivePaymentMethods(array $parameters = [], array $salesChannels = []): array
    {
        $methods = [];

        if (empty($salesChannels)) {
            return [];
        }

        foreach ($salesChannels as $storefront) {
            try {
                $methods = $this->getActivePaymentMethodsForSalesChannel($storefront, $parameters);
            } catch (Exception $e) {
                $this->logger->error(
                    sprintf('Error when loading active payment methods from Mollie for storefront %s', $storefront->getName()),
                    [
                        'error' => $e->getMessage(),
                    ]
                );
            }
        }

        $handledIds = [];

        return array_filter($methods, static function($method) use ($handledIds) {
            $isHandled = in_array($method->id, $handledIds, true);

            if ($isHandled) {
                return false;
            }

            $handledIds[] = $method->id;
            return true;
        });
    }

    /**
     * Returns an array of active payment methods for a given amount in a specific sales channel.
     *
     * @param Cart $cart
     * @param string $currency
     * @param array<SalesChannelEntity> $salesChannels
     * @return array<Method>
     */
    public function getActivePaymentMethodsForAmount(Cart $cart, string $currency, array $salesChannels = []): array
    {
        return $this->getActivePaymentMethods([
            'amount' => [
                'value' => number_format($cart->getPrice()->getTotalPrice(), 2, '.', ''),
                'currency' => strtoupper($currency),
            ]
        ], $salesChannels);
    }

    /**
     * Returns an array of active payment methods for a specific sales channel.
     *
     * @param SalesChannelEntity $salesChannel
     * @param array $parameters
     * @return array<Method>
     * @throws ApiException
     */
    private function getActivePaymentMethodsForSalesChannel(SalesChannelEntity $salesChannel, array $parameters = []): array
    {
        $mollieApiClient = $this->mollieApiFactory->getClient($salesChannel->getId());

        if (!in_array('resource', $parameters, true)) {
            $parameters['resource'] = 'orders';
        }

        if (!in_array('includeWallets', $parameters, true)) {
            $parameters['includeWallets'] = 'applepay';
        }

        return $mollieApiClient->methods
            ->allActive($parameters)
            ->getArrayCopy();
    }
}
