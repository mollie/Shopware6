<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Fixtures\Handler\Category;

use Kiener\MolliePayments\Components\Fixtures\MollieFixtureHandlerInterface;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class CategoryFixture implements MollieFixtureHandlerInterface
{
    public string $CATEGORY_ROOT_ID = '';
    public string $CATEGORY_VOUCHER_ID = '';
    public string $CATEGORY_SUBSCRIPTIONS_ID = '';
    public string $CATEGORY_FAILURES_ID = '';
    public string $CATEGORY_ROUNDING_ID = '';
    public string $CATEGORY_CHEAP_ID = '';

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private $categoryRepository;

    /**
     * @param EntityRepository<CategoryCollection> $categoryRepository
     */
    public function __construct(EntityRepository $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;

        $this->CATEGORY_ROOT_ID = Uuid::fromStringToHex('Mollie');

        $this->CATEGORY_VOUCHER_ID = Uuid::fromStringToHex('Voucher');
        $this->CATEGORY_SUBSCRIPTIONS_ID = Uuid::fromStringToHex('Subscriptions');
        $this->CATEGORY_FAILURES_ID = Uuid::fromStringToHex('Failures');
        $this->CATEGORY_ROUNDING_ID = Uuid::fromStringToHex('Rounding');
        $this->CATEGORY_CHEAP_ID = Uuid::fromStringToHex('Cheap');
    }

    public function install(): void
    {
        $context = new Context(new SystemSource());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'Free time & electronics'));
        $categorySearchResult = $this->categoryRepository->search($criteria, $context);

        /** @var CategoryEntity $rootCategory */
        $rootCategory = $categorySearchResult->first();

        $afterCatId = $rootCategory->getId();
        $cmsPageId = $rootCategory->getCmsPageId();

        $this->createCategory($this->CATEGORY_ROOT_ID, 'Mollie', $context, $afterCatId, $rootCategory->getParentId(), $cmsPageId);

        $this->createCategory($this->CATEGORY_VOUCHER_ID, 'Voucher', $context, $afterCatId, $this->CATEGORY_ROOT_ID, $cmsPageId);
        $this->createCategory($this->CATEGORY_SUBSCRIPTIONS_ID, 'Subscriptions', $context, $afterCatId, $this->CATEGORY_ROOT_ID, $cmsPageId);
        $this->createCategory($this->CATEGORY_FAILURES_ID, 'Failures', $context, $afterCatId, $this->CATEGORY_ROOT_ID, $cmsPageId);
        $this->createCategory($this->CATEGORY_ROUNDING_ID, 'Rounding', $context, $afterCatId, $this->CATEGORY_ROOT_ID, $cmsPageId);
        $this->createCategory($this->CATEGORY_CHEAP_ID, 'Cheap', $context, $afterCatId, $this->CATEGORY_ROOT_ID, $cmsPageId);
    }

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
