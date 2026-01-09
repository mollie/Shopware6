<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Fixture\Tax;

use Mollie\Shopware\Component\Fixture\AbstractFixture;
use Mollie\Shopware\Component\Fixture\FixtureGroup;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Tax\TaxEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class TaxFixture extends AbstractFixture
{
    /**
     * @param EntityRepository<TaxCollection<TaxEntity>> $taxRepository
     */
    public function __construct(
        #[Autowire(service: 'tax.repository')]
        private readonly EntityRepository $taxRepository,
    ) {
    }

    public function getGroup(): FixtureGroup
    {
        return FixtureGroup::DATA;
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function install(Context $context): void
    {
        $upsertData = [];
        $upsertData[] = $this->getTaxData('Standard tax rate (19%)', 19);
        $upsertData[] = $this->getTaxData('Reduced tax rate (7%)', 7);
        $upsertData[] = $this->getTaxData('Free tax rate (0%)', 0);
        $this->taxRepository->upsert($upsertData, $context);
    }

    public function uninstall(Context $context): void
    {
        $taxes = [
            ['id' => Uuid::fromStringToHex('tax-19')],
            ['id' => Uuid::fromStringToHex('tax-7')],
            ['id' => Uuid::fromStringToHex('tax-0')],
        ];
        $this->taxRepository->delete($taxes, $context);
    }

    /**
     * @return array<mixed>
     */
    private function getTaxData(string $name, int $taxRate): array
    {
        return [
            'id' => Uuid::fromStringToHex('tax-' . $taxRate),
            'taxRate' => $taxRate,
            'name' => $name,
        ];
    }
}
