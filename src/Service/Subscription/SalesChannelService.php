<?php declare(strict_types=1);
namespace Kiener\MolliePayments\Service\Subscription;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class SalesChannelService
{
    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var AbstractSalesChannelContextFactory
     */
    private $salesChannelContextFactory;

    /**
     * @param EntityRepositoryInterface $salesChannelRepository
     * @param AbstractSalesChannelContextFactory $salesChannelContextFactory
     */
    public function __construct(
        EntityRepositoryInterface $salesChannelRepository,
        AbstractSalesChannelContextFactory $salesChannelContextFactory
    ) {
        $this->salesChannelRepository = $salesChannelRepository;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
    }

    /**
     * Creating a sales channel context
     *
     * @param string|null $salesChannelId
     * @param string|null $languageId
     * @return SalesChannelContext
     */
    public function createSalesChannelContext(
        string $salesChannelId = null,
        string $languageId = null
    ): SalesChannelContext {
        if (!isset($salesChannelId) || !isset($languageId)) {
            $criteria = new Criteria();
            if (isset($salesChannelId)) {
                $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
            }
            if (isset($languageId)) {
                $criteria->addFilter(new EqualsFilter('languageId', $languageId));
            }

            /** @var SalesChannelEntity $salesChannel */
            $salesChannel = $this->salesChannelRepository->search($criteria, Context::createDefaultContext())->first();
            if ($salesChannel) {
                $salesChannelId = $salesChannel->getId();
                $languageId = $salesChannel->getLanguageId();
            }
        }

        return $this->salesChannelContextFactory->create(
            '',
            $salesChannelId,
            [SalesChannelContextService::LANGUAGE_ID => $languageId]
        );
    }
}
