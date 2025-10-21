<?php
declare(strict_types=1);

namespace Mollie\Integration\Data;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

trait SalesChannelTestBehaviour
{
    use IntegrationTestBehaviour;

    public function findSalesChannelByDomain(string $domain, Context $context): SalesChannelEntity
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('sales_channel.repository');

        $criteria = new Criteria();
        $criteria->addAssociation('domains');
        $criteria->addAssociation('language.locale');
        $criteria->addAssociation('currency');

        $criteria->addFilter(new EqualsFilter('domains.url', $domain));
        $criteria->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));
        $criteria->addFilter(new EqualsFilter('active', true));

        return $repository->search($criteria, $context)->first();
    }

    public function getSalesChannelContext(SalesChannelEntity $salesChannel, array $options = []): SalesChannelContext
    {
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);

        return $salesChannelContextFactory->create(Uuid::fromStringToHex($salesChannel->getName()), $salesChannel->getId(), $options);
    }

    public function getDefaultSalesChannelContext(string $domain = '', array $options = []): SalesChannelContext
    {
        $context = Context::createDefaultContext();

        if ($domain === '') {
            $domain = $_ENV['APP_URL'];
        }

        $salesChannel = $this->findSalesChannelByDomain($domain, $context);

        return $this->getSalesChannelContext($salesChannel, $options);
    }
}
