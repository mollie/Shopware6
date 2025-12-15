<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Fixtures\Handler\SalesChannel;

use Kiener\MolliePayments\Components\Fixtures\MollieFixtureHandlerInterface;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

class SalesChannelFixture implements MollieFixtureHandlerInterface
{
    /**
     * @var EntityRepository<SalesChannelCollection>
     */
    private EntityRepository $repoSalesChannels;

    /**
     * @var EntityRepository<PaymentMethodCollection>
     */
    private EntityRepository $repoPaymentMethods;

    /**
     * @param EntityRepository<SalesChannelCollection> $repoSalesChannels
     * @param EntityRepository<PaymentMethodCollection> $repoPaymentMethods
     */
    public function __construct(EntityRepository $repoSalesChannels, EntityRepository $repoPaymentMethods)
    {
        $this->repoSalesChannels = $repoSalesChannels;
        $this->repoPaymentMethods = $repoPaymentMethods;
    }

    public function install(): void
    {
        $ctx = Context::createDefaultContext();

        // first delete all existing configurations
        // of the specific sales channels
        $salesChannelIds = $this->repoSalesChannels->searchIds(new Criteria(), $ctx)->getIds();

        $this->activatePaymentMethods($ctx);
        $this->assignPaymentMethods($salesChannelIds, $ctx);
    }

    private function activatePaymentMethods(Context $ctx): void
    {
        $paymentUpdates = [];

        $mollieCriteria = new Criteria();
        $mollieCriteria->addFilter(
            new ContainsFilter('handlerIdentifier', 'MolliePayments')
        );

        $molliePaymentMethodIds = $this->repoPaymentMethods->searchIds($mollieCriteria, $ctx)->getIds();

        foreach ($molliePaymentMethodIds as $id) {
            $paymentUpdates[] = [
                'id' => $id,
                'active' => true,
            ];
        }

        $this->repoPaymentMethods->update($paymentUpdates, $ctx);
    }

    /**
     * @param array<mixed> $salesChannelIds
     */
    private function assignPaymentMethods(array $salesChannelIds, Context $ctx): void
    {
        $paymentUpdates = [];
        $molliePaymentMethodIdsPrepared = [];

        $molliePaymentMethodIds = $this->repoPaymentMethods->searchIds(new Criteria(), $ctx)->getIds();

        foreach ($molliePaymentMethodIds as $id) {
            $molliePaymentMethodIdsPrepared[] = [
                'id' => $id,
            ];
        }

        foreach ($salesChannelIds as $id) {
            $paymentUpdates[] = [
                'id' => $id,
                'paymentMethods' => $molliePaymentMethodIdsPrepared,
            ];
        }

        $this->repoSalesChannels->update($paymentUpdates, $ctx);
    }
}
