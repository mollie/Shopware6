<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Payment\Provider;

use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderPriceBuilder;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Method;
use Psr\Log\LoggerInterface;

class ActivePaymentMethodsProvider implements ActivePaymentMethodsProviderInterface
{
    /**
     * @var MollieApiFactory
     */
    private $mollieApiFactory;

    /**
     * @var MollieOrderPriceBuilder
     */
    private $priceFormatter;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(MollieApiFactory $mollieApiFactory, MollieOrderPriceBuilder $priceFormatter, LoggerInterface $logger)
    {
        $this->mollieApiFactory = $mollieApiFactory;
        $this->priceFormatter = $priceFormatter;
        $this->logger = $logger;
    }

    /**
     * Returns an array of active payment methods for a given amount in a specific sales channel.
     *
     * @param array<mixed> $salesChannelIDs
     *
     * @return Method[]
     */
    public function getActivePaymentMethodsForAmount(float $price, string $currency, string $billingCountryCode, array $salesChannelIDs): array
    {
        if ($price < 0.01) {
            return [];
        }

        $params = [
            'amount' => [
                'value' => $this->priceFormatter->formatValue($price),
                'currency' => strtoupper($currency),
            ],
        ];

        if ($billingCountryCode !== '') {
            $params['billingCountry'] = $billingCountryCode;
        }

        return $this->getActivePaymentMethods($params, $salesChannelIDs);
    }

    /**
     * Returns an array of active payment methods.
     *
     * @param array<mixed> $parameters
     * @param array<string> $salesChannelIDs
     *
     * @return array<Method>
     */
    private function getActivePaymentMethods(array $parameters, array $salesChannelIDs): array
    {
        $allFoundMethods = [];

        if (empty($salesChannelIDs)) {
            return [];
        }

        foreach ($salesChannelIDs as $channelId) {
            try {
                $shopMethods = $this->requestMollieMethods($channelId, $parameters);

                foreach ($shopMethods as $shopMethod) {
                    $found = false;

                    /** @var Method $method */
                    foreach ($allFoundMethods as $method) {
                        if ($method->id === $shopMethod->id) {
                            $found = true;
                            break;
                        }
                    }

                    if (! $found) {
                        $allFoundMethods[] = $shopMethod;
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error(
                    'Error when loading active payment methods from Mollie for storefront: ' . $channelId,
                    [
                        'error' => $e->getMessage(),
                    ]
                );
            }
        }

        return $allFoundMethods;
    }

    /**
     * Returns an array of active payment methods for a specific sales channel.
     *
     * @param array<mixed> $parameters
     *
     * @throws ApiException
     *
     * @return array<Method>
     */
    private function requestMollieMethods(string $salesChannelId, array $parameters): array
    {
        $mollieApiClient = $this->mollieApiFactory->getClient($salesChannelId);

        if (! in_array('resource', $parameters, true)) {
            $parameters['resource'] = 'orders';
        }

        if (! in_array('includeWallets', $parameters, true)) {
            $parameters['includeWallets'] = 'applepay';
        }

        return $mollieApiClient->methods
            ->allActive($parameters)
            ->getArrayCopy()
        ;
    }
}
