<?php

namespace MolliePayments\Fixtures\SalesChannel;


use Basecom\FixturePlugin\Fixture;
use Basecom\FixturePlugin\FixtureBag;
use Kiener\MolliePayments\Repository\SalesChannel\SalesChannelRepositoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;


class SalesChannelFixture extends Fixture
{

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $repoSalesChannels;

    /**
     * @var EntityRepositoryInterface
     */
    private $repoPaymentMethods;


    /**
     * @param SalesChannelRepositoryInterface $repoSalesChannels
     * @param EntityRepositoryInterface $repoPaymentMethods
     */
    public function __construct(SalesChannelRepositoryInterface $repoSalesChannels, EntityRepositoryInterface $repoPaymentMethods)
    {
        $this->repoSalesChannels = $repoSalesChannels;
        $this->repoPaymentMethods = $repoPaymentMethods;
    }


    /**
     * @return string[]
     */
    public function groups(): array
    {
        return [
            'mollie',
            'mollie-setup',
        ];
    }

    /**
     * @param FixtureBag $bag
     * @return void
     */
    public function load(FixtureBag $bag): void
    {
        $ctx = Context::createDefaultContext();

        # first delete all existing configurations
        # of the specific sales channels
        $salesChannelIds = $this->repoSalesChannels->searchIds(new Criteria([]), $ctx)->getIds();

        $this->activatePaymentMethods($ctx);
        $this->assignPaymentMethods($salesChannelIds, $ctx);
    }

    /**
     * @param array<mixed> $salesChannelIds
     * @param Context $ctx
     * @return void
     */
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
     * @param Context $ctx
     * @return void
     */
    private function assignPaymentMethods(array $salesChannelIds, Context $ctx): void
    {
        $paymentUpdates = [];
        $molliePaymentMethodIdsPrepared = [];

        $molliePaymentMethodIds = $this->repoPaymentMethods->searchIds(new Criteria([]), $ctx)->getIds();


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
