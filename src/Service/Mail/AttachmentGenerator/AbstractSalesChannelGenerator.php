<?php
declare(strict_types=1);

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

    public function __construct(EntityRepository $salesChannelRepository)
    {
        $this->salesChannelRepository = $salesChannelRepository;
    }

    /**
     * @return string[]
     */
    protected function getSalesChannelIds(Context $context): array
    {
        return $this->getSalesChannels($context)->map(function ($salesChannel) {
            return $salesChannel->getId();
        });
    }

    protected function getSalesChannels(Context $context): SalesChannelCollection
    {
        /** @var SalesChannelCollection */
        return $this->salesChannelRepository->search(
            (new Criteria())->addAssociation('paymentMethods.availabilityRule'),
            $context
        )->getEntities();
    }
}
