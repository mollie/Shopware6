<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Data;

use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

trait PaymentMethodTestBehaviour
{
    use IntegrationTestBehaviour;

    public function getPaymentMethodByTechnicalName(string $technicalName, Context $context): PaymentMethodEntity
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('payment_method.repository');
        $criteria = new Criteria();

        $criteria->addFilter(new EqualsFilter('technicalName', 'payment_mollie_' . $technicalName));
        $searchResult = $repository->search($criteria, $context);
        /** @var ?PaymentMethodEntity $firstPaymentMethod */
        $firstPaymentMethod = $searchResult->first();
        if ($firstPaymentMethod === null) {
            throw new \RuntimeException(sprintf('Payment method not found for technical name "payment_mollie_%s"', $technicalName));
        }

        return $firstPaymentMethod;
    }

    public function getPaymentMethodByIdentifier(string $handlerIdentifier, Context $context): PaymentMethodEntity
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('payment_method.repository');
        $criteria = new Criteria();

        $criteria->addFilter(new EqualsFilter('handlerIdentifier', $handlerIdentifier));
        $searchResult = $repository->search($criteria, $context);
        $firstPaymentMethod = $searchResult->first();
        if ($firstPaymentMethod === null) {
            throw new \RuntimeException(sprintf('Payment method not found for handler "%s"', $handlerIdentifier));
        }

        return $firstPaymentMethod;
    }

    public function activatePaymentMethod(PaymentMethodEntity $paymentMethod, Context $context): void
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('payment_method.repository');

        $data = [
            'id' => $paymentMethod->getId(),
            'active' => true,
        ];
        $repository->upsert([$data], $context);
    }

    private function assignPaymentMethodToSalesChannel(PaymentMethodEntity $paymentMethod, SalesChannelEntity $salesChannel, Context $context): void
    {
        $repository = $this->getContainer()->get('sales_channel_payment_method.repository');
        $data = [
            'salesChannelId' => $salesChannel->getId(),
            'paymentMethodId' => $paymentMethod->getId(),
        ];
        $repository->upsert([$data], $context);
    }
}
