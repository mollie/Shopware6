<?php

namespace Kiener\MolliePayments\Service\Mail\AttachmentGenerator;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

abstract class AbstractSalesChannelGenerator implements GeneratorInterface
{
    /**
     * @var EntityRepository
     */
    protected $salesChannelRepository;

    /**
     * @param EntityRepository $salesChannelRepository
     */
    public function __construct(EntityRepository $salesChannelRepository)
    {
        $this->salesChannelRepository = $salesChannelRepository;
    }

    /**
     * @param Context $context
     * @return array<mixed>
     */
    protected function getSalesChannelIds(Context $context): array
    {
        return $this->getSalesChannels($context)->map(function ($salesChannel) {
            return $salesChannel->getId();
        });
    }

    /**
     * @param Context $context
     * @return SalesChannelCollection
     */
    protected function getSalesChannels(Context $context): SalesChannelCollection
    {
        /** @var SalesChannelCollection $salesChannels */
        $salesChannels = $this->salesChannelRepository->search(
            (new Criteria())->addAssociation('paymentMethods.availabilityRule'),
            $context
        )->getEntities();
        return $salesChannels;
    }
}
