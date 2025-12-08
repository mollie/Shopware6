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

class CategoryFixture implements MollieFixtureHandlerInterface
{
    public string $CATEGORY_ROOT_ID = '7c478959c218087ffc4ad5d96e7f66a6';
    public string $CATEGORY_VOUCHER_ID = 'be686376cddb23d0227444ccc3c4b5b7';
    public string $CATEGORY_SUBSCRIPTIONS_ID = '4ca2c509994c2776d0880357b4e8e5be';
    public string $CATEGORY_FAILURES_ID = 'a62299522cf413e43d541ba8b99f0179';
    public string $CATEGORY_ROUNDING_ID = 'f81873c32fc169ee4afa8ea831f6aba4';
    public string $CATEGORY_CHEAP_ID = '9e15f82bd051abfda8c237d59dcd6c04';

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private $categoryRepository;

    /**
     * @param EntityRepository<CategoryCollection> $categoryRepository
     */
    public function __construct($categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }

    public function install(): void
    {
        $context = new Context(new SystemSource());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', null));
        $categorySearchResult = $this->categoryRepository->search($criteria, $context);

        /** @var CategoryEntity $rootCategory */
        $rootCategory = $categorySearchResult->first();

        $afterCatId = null;
        $cmsPageId = null;

        $this->createCategory($this->CATEGORY_ROOT_ID, 'Mollie', $context, $afterCatId, $rootCategory->getId(), $cmsPageId);

        $this->createCategory($this->CATEGORY_VOUCHER_ID, 'Voucher', $context, $afterCatId, $this->CATEGORY_ROOT_ID, $cmsPageId);
        $this->createCategory($this->CATEGORY_SUBSCRIPTIONS_ID, 'Subscriptions', $context, $afterCatId, $this->CATEGORY_ROOT_ID, $cmsPageId);
        $this->createCategory($this->CATEGORY_FAILURES_ID, 'Failures', $context, $afterCatId, $this->CATEGORY_ROOT_ID, $cmsPageId);
        $this->createCategory($this->CATEGORY_ROUNDING_ID, 'Rounding', $context, $afterCatId, $this->CATEGORY_ROOT_ID, $cmsPageId);
        $this->createCategory($this->CATEGORY_CHEAP_ID, 'Regular Products', $context, $afterCatId, $this->CATEGORY_ROOT_ID, $cmsPageId);
    }

    public function uninstall(): void
    {
        $this->categoryRepository->delete(
            [
                ['id' => $this->CATEGORY_ROOT_ID],
                ['id' => $this->CATEGORY_VOUCHER_ID],
                ['id' => $this->CATEGORY_SUBSCRIPTIONS_ID],
                ['id' => $this->CATEGORY_FAILURES_ID],
                ['id' => $this->CATEGORY_ROUNDING_ID],
                ['id' => $this->CATEGORY_CHEAP_ID],
            ],
            Context::createDefaultContext()
        );
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
