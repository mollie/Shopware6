<?php

namespace Kiener\MolliePayments\Service\Mail\AttachmentGenerator;

use Kiener\MolliePayments\Exception\SalesChannelPaymentMethodsException;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\SalesChannel\SalesChannelDataExtractor;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Method;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class PaymentMethodGenerator extends AbstractSalesChannelGenerator
{
    /**
     * @var MollieApiFactory
     */
    protected $apiFactory;

    /**
     * @var EntityRepositoryInterface
     */
    protected $paymentMethodRepository;

    /**
     * @var SalesChannelDataExtractor
     */
    protected $salesChannelDataExtractor;

    public function __construct(
        EntityRepositoryInterface $salesChannelRepository,
        MollieApiFactory          $apiFactory,
        EntityRepositoryInterface $paymentMethodRepository,
        SalesChannelDataExtractor $salesChannelDataExtractor
    )
    {
        parent::__construct($salesChannelRepository);

        $this->apiFactory = $apiFactory;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->salesChannelDataExtractor = $salesChannelDataExtractor;
    }

    /**
     * @inheritDoc
     */
    public function generate(Context $context): array
    {
        $fileContent = [];
        $fileContent = array_merge($fileContent, $this->getShopwarePaymentMethodStatus($context));

        /** @var SalesChannelEntity $salesChannel */
        foreach ($this->getSalesChannels($context) as $salesChannel) {
            $fileContent = array_merge($fileContent, $this->getSalesChannelPaymentMethodAssignments($salesChannel));
        }

        return [
            'content' => implode("\r\n", $fileContent),
            'fileName' => 'payment_method_data.txt',
            'mimeType' => 'text/plain',
        ];
    }

    public function getShopwarePaymentMethodStatus($context): array
    {
        $fileContent = [];
        $fileContent[] = '[ Payment methods ]';

        $criteria = new Criteria();
        $criteria->addFilter(new ContainsFilter('handlerIdentifier', 'MolliePayments'));

        /** @var PaymentMethodCollection $paymentMethods */
        $paymentMethods = $this->paymentMethodRepository->search($criteria, $context)->getEntities();

        /** @var PaymentMethodEntity $paymentMethod */
        foreach ($paymentMethods as $paymentMethod) {
            $fileContent[] = $paymentMethod->getName() . ': ' . ($paymentMethod->getActive() ? 'Active' : 'Inactive');
        }

        $fileContent[] = '';

        return $fileContent;
    }

    public function getSalesChannelPaymentMethodAssignments(SalesChannelEntity $salesChannel): array
    {
        $fileContent = [];
        $fileContent[] = '';
        $fileContent[] = '[ ' . $salesChannel->getName() . ' ]';

        try {
            $fileContent[] = '= Assigned Payment Methods =';

            $paymentMethods = $this->salesChannelDataExtractor->extractPaymentMethods($salesChannel);

            $paymentMethods->sort(function (PaymentMethodEntity $a, PaymentMethodEntity $b) {
                return strnatcmp($a->getPluginId(), $b->getPluginId()) ?: strnatcmp($a->getName(), $b->getName());
            });

            /** @var PaymentMethodEntity $paymentMethod */
            foreach ($paymentMethods as $paymentMethod) {
                $fileContent[] = $paymentMethod->getName() . ($paymentMethod->getId() === $salesChannel->getPaymentMethodId() ? ' (Default)' : '');
            }
        } catch (SalesChannelPaymentMethodsException $e) {
            $fileContent[] = 'No payment methods available';
        } finally {
            $fileContent[] = '';
        }

        $fileContent = array_merge($fileContent, $this->getLivePaymentMethodData($salesChannel));
        $fileContent = array_merge($fileContent, $this->getTestPaymentMethodData($salesChannel));

        return $fileContent;
    }

    protected function getLivePaymentMethodData(SalesChannelEntity $salesChannel): array
    {
        $fileContent = [];
        $fileContent[] = '= Status in Mollie for Live API key =';

        $apiClient = $this->apiFactory->getLiveClient($salesChannel->getId());
        return array_merge($fileContent, $this->getMolliePaymentMethodStatus($apiClient));
    }

    protected function getTestPaymentMethodData(SalesChannelEntity $salesChannel): array
    {
        $fileContent = [];
        $fileContent[] = '= Status in Mollie for Test API key =';

        $apiClient = $this->apiFactory->getTestClient($salesChannel->getId());
        return array_merge($fileContent, $this->getMolliePaymentMethodStatus($apiClient));
    }

    protected function getMolliePaymentMethodStatus(MollieApiClient $apiClient): array
    {
        $fileContent = [];
        try {
            $methods = $apiClient->methods->allAvailable();

            /** @var Method $method */
            foreach ($methods as $method) {
                $fileContent[] = sprintf('%s: %s', $method->description, $method->status ?? 'Unknown');
            }
        } catch (ApiException $e) {
            $fileContent[] = 'Could not get payment methods from Mollie, perhaps the API key is invalid';
        } finally {
            $fileContent[] = '';
        }

        return $fileContent;
    }
}
