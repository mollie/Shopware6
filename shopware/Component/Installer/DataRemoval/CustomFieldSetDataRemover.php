<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Installer\DataRemoval;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Removes the custom field set definitions the plugin registers. Deleting a set cascades to its
 * custom fields; the actual values stored on entities are removed by
 * {@see CustomFieldValueDataRemover}.
 */
final class CustomFieldSetDataRemover implements DataRemoverInterface
{
    private const CUSTOM_FIELD_SET_NAMES = [
        'mollie_payments_address',
        'mollie_payments_product',
    ];

    /**
     * @param EntityRepository<\Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<\Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity>> $customFieldSetRepository
     */
    public function __construct(
        #[Autowire(service: 'custom_field_set.repository')]
        private readonly EntityRepository $customFieldSetRepository
    ) {
    }

    public function remove(Context $context): void
    {
        $criteria = new Criteria();
        $nameFilter = new EqualsAnyFilter('name', self::CUSTOM_FIELD_SET_NAMES);
        $criteria->addFilter($nameFilter);

        $ids = $this->customFieldSetRepository->searchIds($criteria, $context)->getIds();
        if (count($ids) === 0) {
            return;
        }

        $deletes = [];
        foreach ($ids as $id) {
            $deletes[] = ['id' => $id];
        }

        $this->customFieldSetRepository->delete($deletes, $context);
    }
}
