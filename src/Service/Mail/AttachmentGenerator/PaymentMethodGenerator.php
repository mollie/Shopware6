<?php

namespace Kiener\MolliePayments\Service\Mail\AttachmentGenerator;

use Kiener\MolliePayments\Exception\SalesChannelPaymentMethodsException;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Repository\PaymentMethod\PaymentMethodRepositoryInterface;
use Kiener\MolliePayments\Repository\SalesChannel\SalesChannelRepositoryInterface;
use Kiener\MolliePayments\Service\SalesChannel\SalesChannelDataExtractor;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Method;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
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
     * @var PaymentMethodRepositoryInterface
     */
    protected $paymentMethodRepository;

    /**
     * @var SalesChannelDataExtractor
     */
    protected $salesChannelDataExtractor;

    /**
     * @param SalesChannelRepositoryInterface $salesChannelRepository
     * @param MollieApiFactory $apiFactory
     * @param PaymentMethodRepositoryInterface $paymentMethodRepository
     * @param SalesChannelDataExtractor $salesChannelDataExtractor
     */
    public function __construct(SalesChannelRepositoryInterface $salesChannelRepository, MollieApiFactory $apiFactory, PaymentMethodRepositoryInterface $paymentMethodRepository, SalesChannelDataExtractor $salesChannelDataExtractor)
    {
        parent::__construct($salesChannelRepository);

        $this->apiFactory = $apiFactory;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->salesChannelDataExtractor = $salesChannelDataExtractor;
    }

    /**
     * @param Context $context
     * @return array<mixed>
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

    /**
     * @param Context $context
     * @return array<mixed>
     */
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

    /**
     * @param SalesChannelEntity $salesChannel
     * @return array<mixed>
     */
    public function getSalesChannelPaymentMethodAssignments(SalesChannelEntity $salesChannel): array
    {
        $fileContent = [];
        $fileContent[] = '';
        $fileContent[] = '[ ' . $salesChannel->getName() . ' ]';

        try {
            $fileContent[] = '= Assigned Payment Methods =';

            $paymentMethods = $this->salesChannelDataExtractor->extractPaymentMethods($salesChannel);

            $paymentMethods->sort(function (PaymentMethodEntity $a, PaymentMethodEntity $b) {
                return strnatcmp((string)$a->getPluginId(), (string)$b->getPluginId()) ?: strnatcmp((string)$a->getName(), (string)$b->getName());
            });

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

    /**
     * @param SalesChannelEntity $salesChannel
     * @return array<mixed>
     */
    protected function getLivePaymentMethodData(SalesChannelEntity $salesChannel): array
    {
        $fileContent = [];
        $fileContent[] = '= Status in Mollie for Live API key =';

        $apiClient = $this->apiFactory->getLiveClient($salesChannel->getId());
        return array_merge($fileContent, $this->getMolliePaymentMethodStatus($apiClient));
    }

    /**
     * @param SalesChannelEntity $salesChannel
     * @return array<mixed>
     */
    protected function getTestPaymentMethodData(SalesChannelEntity $salesChannel): array
    {
        $fileContent = [];
        $fileContent[] = '= Status in Mollie for Test API key =';

        $apiClient = $this->apiFactory->getTestClient($salesChannel->getId());
        return array_merge($fileContent, $this->getMolliePaymentMethodStatus($apiClient));
    }

    /**
     * @param MollieApiClient $apiClient
     * @return array<mixed>
     */
    protected function getMolliePaymentMethodStatus(MollieApiClient $apiClient): array
    {
        $fileContent = [];
        try {
            $methods = $apiClient->methods->allAvailable();

            /** @var Method $method */
            foreach ($methods as $method) {
                $fileContent[] = sprintf('%s: %s', $method->description, (string)$method->status);
            }
        } catch (ApiException $e) {
            $fileContent[] = 'Could not get payment methods from Mollie, perhaps the API key is invalid';
        } finally {
            $fileContent[] = '';
        }

        return $fileContent;
    }
}
