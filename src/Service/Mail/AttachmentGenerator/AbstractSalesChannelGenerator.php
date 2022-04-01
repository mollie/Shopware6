<?php

namespace Kiener\MolliePayments\Service\Mail\AttachmentGenerator;

use Kiener\MolliePayments\Service\SalesChannel\SalesChannelDataExtractor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

abstract class AbstractSalesChannelGenerator implements GeneratorInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    protected $salesChannelRepository;

    /**
     * @param EntityRepositoryInterface $salesChannelRepository
     */
    public function __construct(
        EntityRepositoryInterface $salesChannelRepository
    )
    {
        $this->salesChannelRepository = $salesChannelRepository;
    }

    /**
     * @param Context $context
     * @return array<string, string>
     */
    protected function getSalesChannelIds(Context $context): array
    {
        /** @var SalesChannelEntity $salesChannel */
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
