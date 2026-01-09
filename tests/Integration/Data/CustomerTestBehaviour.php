<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Data;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

trait CustomerTestBehaviour
{
    use IntegrationTestBehaviour;
    use SalesChannelTestBehaviour;
    use RequestTestBehaviour;

    public function loginOrCreateAccount(string $email, SalesChannelContext $salesChannelContext): string
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('customer.repository');
        $criteria = new Criteria();

        $criteria->addFilter(new EqualsFilter('email', $email));

        return (string) $repository->searchIds($criteria, $salesChannelContext->getContext())->firstId();
    }

    public function getUserAddressByIso(string $isoCode, SalesChannelContext $salesChannelContext): IdSearchResult
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('customer_address.repository');
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('country.iso', $isoCode))
            ->addFilter(new EqualsFilter('customerId', $salesChannelContext->getCustomer()->getId()))
        ;

        return $repository->searchIds($criteria, $salesChannelContext->getContext());
    }
}
