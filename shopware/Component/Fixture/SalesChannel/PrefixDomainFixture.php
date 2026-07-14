<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Fixture\SalesChannel;

use Mollie\Shopware\Component\Fixture\AbstractFixture;
use Mollie\Shopware\Component\Fixture\FixtureGroup;
use Mollie\Shopware\Component\Fixture\SalesChannelTrait;
use Psr\Container\ContainerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adds a storefront domain that serves the shop under a URL path prefix - a "virtual url" like
 * https://localhost/mollie-e2e. This reproduces the setup of merchants whose sales channel domains
 * carry a language path, which is the only situation in which the Mollie return and webhook urls have
 * to include that prefix. The E2E checkout test relies on this domain being present.
 *
 * A prefixed domain is created for every existing root domain (one without a path), so the shop is
 * reachable under the prefix on every scheme/host the shop already answers on (e.g. http and https).
 *
 * The prefix is duplicated in the Cypress test (checkout-path-prefix.cy.js); keep both in sync.
 */
final class PrefixDomainFixture extends AbstractFixture
{
    use SalesChannelTrait;

    public const DOMAIN_PREFIX = 'mollie-e2e';

    /**
     * @param EntityRepository<SalesChannelDomainCollection<SalesChannelDomainEntity>> $domainRepository
     */
    public function __construct(
        #[Autowire(service: 'service_container')]
        private readonly ContainerInterface $container,
        #[Autowire(service: 'sales_channel_domain.repository')]
        private readonly EntityRepository $domainRepository
    ) {
    }

    public function getGroup(): FixtureGroup
    {
        return FixtureGroup::SETUP;
    }

    public function install(Context $context): void
    {
        $salesChannelId = $this->getSalesChannelId($context);

        // self-cleaning: drop any previously created prefixed domain so repeated runs don't
        // collide on the unique domain url
        $this->removePrefixedDomains($salesChannelId, $context);

        $upsertData = [];
        foreach ($this->getRootDomains($salesChannelId, $context) as $root) {
            $upsertData[] = [
                'id' => md5($root->getId() . self::DOMAIN_PREFIX),
                'salesChannelId' => $salesChannelId,
                'languageId' => $root->getLanguageId(),
                'currencyId' => $root->getCurrencyId(),
                'snippetSetId' => $root->getSnippetSetId(),
                'url' => rtrim($root->getUrl(), '/') . '/' . self::DOMAIN_PREFIX,
            ];
        }

        if (count($upsertData) > 0) {
            $this->domainRepository->upsert($upsertData, $context);
        }
    }

    public function uninstall(Context $context): void
    {
        $this->removePrefixedDomains($this->getSalesChannelId($context), $context);
    }

    /**
     * @return list<SalesChannelDomainEntity>
     */
    private function getRootDomains(string $salesChannelId, Context $context): array
    {
        $roots = [];
        foreach ($this->getChannelDomains($salesChannelId, $context) as $domain) {
            $path = parse_url($domain->getUrl(), PHP_URL_PATH);
            if ($path === null || $path === '' || $path === '/') {
                $roots[] = $domain;
            }
        }

        return $roots;
    }

    private function removePrefixedDomains(string $salesChannelId, Context $context): void
    {
        $suffix = '/' . self::DOMAIN_PREFIX;

        $deleteData = [];
        foreach ($this->getChannelDomains($salesChannelId, $context) as $domain) {
            if (str_ends_with($domain->getUrl(), $suffix)) {
                $deleteData[] = ['id' => $domain->getId()];
            }
        }

        if (count($deleteData) > 0) {
            $this->domainRepository->delete($deleteData, $context);
        }
    }

    private function getChannelDomains(string $salesChannelId, Context $context): SalesChannelDomainCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));

        return $this->domainRepository->search($criteria, $context)->getEntities();
    }
}
