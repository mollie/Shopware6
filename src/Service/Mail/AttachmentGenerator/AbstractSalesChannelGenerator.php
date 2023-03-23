<?php

namespace Kiener\MolliePayments\Service\Mail\AttachmentGenerator;

use Kiener\MolliePayments\Repository\SalesChannel\SalesChannelRepository;
use Kiener\MolliePayments\Service\SalesChannel\SalesChannelDataExtractor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

abstract class AbstractSalesChannelGenerator implements GeneratorInterface
{
    /**
     * @var SalesChannelRepository
     */
    protected $salesChannelRepository;

    /**
     * @param SalesChannelRepository $salesChannelRepository
     */
    public function __construct(SalesChannelRepository $salesChannelRepository)
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
