<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Fixture\Category;

use Mollie\Shopware\Component\Fixture\AbstractFixture;
use Mollie\Shopware\Component\Fixture\FixtureGroup;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class MollieCategoriesFixture extends AbstractFixture
{
    /**
     * @param EntityRepository<CategoryCollection<CategoryEntity>> $categoryRepository
     */
    public function __construct(
        #[Autowire(service: 'category.repository')]
        private readonly EntityRepository $categoryRepository
    ) {
    }

    public function getPriority(): int
    {
        return 100;
    }


    public function getGroup(): FixtureGroup
    {
        return FixtureGroup::DATA;
    }

    public function install(Context $context): void
    {
        $rootCategory = $this->getRootCategoryId($context);
        $categoryTree = $this->getCategoryTree($rootCategory);

        $upsertData = [];
        foreach ($categoryTree as $categoryData) {
            $upsertData[] = $this->getCategoryData($categoryData['id'], $categoryData['name'], $categoryData['parentId']);
        }

        $this->categoryRepository->upsert($upsertData, $context);
    }

    public function uninstall(Context $context): void
    {
        $categoryTree = $this->getCategoryTree('root');
        $deleteData = [];
        foreach ($categoryTree as $categoryData) {
            $deleteData[] = [
                'id' => $categoryData['id'],
            ];
        }
        $this->categoryRepository->delete($deleteData, $context);
    }

    /**
     * @return array<mixed>
     */
    private function getCategoryTree(string $rootCategoryId): array
    {
        $mollieRootId = Uuid::fromStringToHex('mollie');

        return [
            [
                'id' => $mollieRootId,
                'name' => 'Mollie',
                'parentId' => $rootCategoryId,
            ],
            [
                'id' => Uuid::fromStringToHex('mollie-regular'),
                'name' => 'Regular Products',
                'parentId' => $mollieRootId,
            ],
            [
                'id' => Uuid::fromStringToHex('mollie-voucher'),
                'name' => 'Voucher',
                'parentId' => $mollieRootId,
            ],
            [
                'id' => Uuid::fromStringToHex('mollie-subscriptions'),
                'name' => 'Subscriptions',
                'parentId' => $mollieRootId,
            ],
            [
                'id' => Uuid::fromStringToHex('mollie-rounding'),
                'name' => 'Rounding',
                'parentId' => $mollieRootId,
            ],
            [
                'id' => Uuid::fromStringToHex('mollie-failures'),
                'name' => 'Credit Card Errors',
                'parentId' => $mollieRootId,
            ],
        ];
    }

    private function getRootCategoryId(Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', null));

        return (string) $this->categoryRepository->searchIds($criteria, $context)->firstId();
    }

    /**
     * @return array<mixed>
     */
    private function getCategoryData(string $id, string $name, string $parentId): array
    {
        return [
            'id' => $id,
            'translations' => [
                'de-DE' => [
                    'name' => $name,
                ],
                'en-GB' => [
                    'name' => $name,
                ],
            ],
            'productAssignmentType' => 'product',
            'level' => 2,
            'active' => true,
            'displayNestedProducts' => true,
            'visible' => true,
            'type' => 'page',
            'cmsPageId' => null,
            'parentId' => $parentId,
            'afterCategoryId' => null,
        ];
    }
}
