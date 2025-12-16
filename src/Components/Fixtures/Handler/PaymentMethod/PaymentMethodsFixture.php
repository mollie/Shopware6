<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Fixtures\Handler\PaymentMethod;

use Kiener\MolliePayments\Components\Fixtures\MollieFixtureHandlerInterface;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

class PaymentMethodsFixture implements MollieFixtureHandlerInterface
{
    /**
     * @var EntityRepository<SalesChannelCollection>
     */
    private $repoSalesChannels;

    /**
     * @var EntityRepository<PaymentMethodCollection>
     */
    private $repoPaymentMethods;

    /**
     * @param EntityRepository<SalesChannelCollection> $repoSalesChannels
     * @param EntityRepository<PaymentMethodCollection> $repoPaymentMethods
     */
    public function __construct($repoSalesChannels, $repoPaymentMethods)
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

    public function uninstall(): void
    {
        // do nothing in this case
        // we dont want to unassign things again
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
