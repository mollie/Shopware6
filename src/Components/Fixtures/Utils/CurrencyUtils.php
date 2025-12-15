<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Fixtures\Utils;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Currency\CurrencyEntity;

class CurrencyUtils
{
    /**
     * @var EntityRepository<CurrencyCollection>
     */
    private $currencyRepository;

    /**
     * @param EntityRepository<CurrencyCollection> $currencyRepository
     */
    public function __construct($currencyRepository)
    {
        $this->currencyRepository = $currencyRepository;
    }

    public function getCurrency(string $iso): CurrencyEntity
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('isoCode', $iso))
            ->setLimit(1)
        ;

        $currency = $this->currencyRepository
            ->search($criteria, Context::createDefaultContext())
            ->first()
        ;

        if (! $currency instanceof CurrencyEntity) {
            throw new \RuntimeException(sprintf('Currency with ISO code "%s" not found.', $iso));
        }

        return $currency;
    }
}
