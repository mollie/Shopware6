<?php


namespace MolliePayments\Shopware\Fixtures\Category;

use Basecom\FixturePlugin\Fixture;
use Basecom\FixturePlugin\FixtureBag;
use Basecom\FixturePlugin\FixtureHelper;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class CategoryFixture extends Fixture
{

    /**
     * @var EntityRepository
     */
    private $categoryRepository;


    /**
     * @param FixtureHelper $helper
     * @param EntityRepository $categoryRepository
     */
    public function __construct(FixtureHelper $helper, EntityRepository $categoryRepository)
    {
        $this->helper = $helper;
        $this->categoryRepository = $categoryRepository;
    }


    /**
     * @return string[]
     */
    public function groups(): array
    {
        return [
            'mollie',
            'mollie-demodata',
        ];
    }


    /**
     * @param FixtureBag $bag
     * @return void
     */
    public function load(): void
    {
        $context = new Context(new SystemSource());
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'Free time & electronics'));
        $categorySearchResult = $this->categoryRepository->search($criteria, $context);

        /** @var CategoryEntity $category */
        $category = $categorySearchResult->first();

        $afterCatId = $category->getId();
        $parentId = $category->getParentId();
        $cmsPageId = $category->getCmsPageId();


        $this->createCategory(Uuid::fromStringToHex('Voucher'), "Voucher", $context, $afterCatId, $parentId, $cmsPageId);
        $this->createCategory(Uuid::fromStringToHex('Subscriptions'), "Subscriptions", $context, $afterCatId, $parentId, $cmsPageId);
        $this->createCategory(Uuid::fromStringToHex('Failures'), "Failures", $context, $afterCatId, $parentId, $cmsPageId);
        $this->createCategory(Uuid::fromStringToHex('Rounding'), "Rounding", $context, $afterCatId, $parentId, $cmsPageId);
        $this->createCategory(Uuid::fromStringToHex('Cheap'), "Cheap", $context, $afterCatId, $parentId, $cmsPageId);
    }

    /**
     * @param string $id
     * @param string $name
     * @param null|string $afterCategoryId
     */
    private function createCategory(string $id, string $name, Context $context, ?string $afterCategoryId, ?string $parentId, ?string $cmsPageId): void
    {

        $this->categoryRepository->upsert([
            [
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
                'cmsPageId' => $cmsPageId,
                'parentId' => $parentId,
                'afterCategoryId' => $afterCategoryId,
            ],
        ], $context);
    }
}
