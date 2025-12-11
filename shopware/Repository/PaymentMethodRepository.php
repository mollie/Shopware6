<?php
declare(strict_types=1);

namespace Mollie\Shopware\Repository;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PaymentMethodRepository implements PaymentMethodRepositoryInterface
{
    /**
     * @param EntityRepository<PaymentMethodCollection<PaymentMethodEntity>> $paymentMethodRepository */
    public function __construct(
        #[Autowire(service: 'payment_method.repository')]
        private EntityRepository $paymentMethodRepository
    ) {
    }

    public function getIdForPaymentMethod(string $handlerIdentifier, string $salesChannelId, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('handlerIdentifier', $handlerIdentifier));
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsFilter('salesChannels.id', $salesChannelId));
        $criteria->setLimit(1);

        $searchResult = $this->paymentMethodRepository->searchIds($criteria, $context);

        return $searchResult->firstId();
    }
}
