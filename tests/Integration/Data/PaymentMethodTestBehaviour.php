<?php
declare(strict_types=1);

namespace Mollie\Integration\Data;

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

    public function getPaymentMethodByIdentifier(string $handlerIdentifier, Context $context): PaymentMethodEntity
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('payment_method.repository');
        $criteria = new Criteria();

        $criteria->addFilter(new EqualsFilter('handlerIdentifier', $handlerIdentifier));

        return $repository->search($criteria, $context)->first();
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

    private function assignPaymentMethodToSalesChannel(PaymentMethodEntity $paymentMethod, SalesChannelEntity $salesChannel, Context $getContext)
    {
        $repository = $this->getContainer()->get('sales_channel_payment_method.repository');
        $data = [
            'salesChannelId' => $salesChannel->getId(),
            'paymentMethodId' => $paymentMethod->getId(),
        ];
        $repository->upsert([$data], $getContext);
    }
}
