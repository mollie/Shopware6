<?php


namespace MolliePayments\Fixtures\Category;

use Basecom\FixturePlugin\Fixture;
use Basecom\FixturePlugin\FixtureBag;
use Basecom\FixturePlugin\FixtureHelper;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

class CategoryFixture extends Fixture
{
    /**
     * @var FixtureHelper
     */
    private $helper;

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
    public function load(FixtureBag $bag): void
    {
        $appendCategory = $this->helper->Category()->getByName('Free time & electronics');

        if (!$appendCategory instanceof CategoryEntity) {
            $appendCategory = $this->helper->Category()->getFirst();
        }

        $afterCatId = ($appendCategory instanceof CategoryEntity) ? $appendCategory->getId() : null;

        $this->createCategory('0d8eefdd6d12456335280e2ff42431b9', "Voucher", $afterCatId);
        $this->createCategory('0d9eefdd6d12456335280e2ff42431b2', "Subscriptions", $afterCatId);
        $this->createCategory('0d9eefdd6d12456335280e2ff42431b9', "Failures", $afterCatId);
        $this->createCategory('2a2eefdd6d12456335280e2ff42431b9', "Rounding", $afterCatId);
    }

    /**
     * @param string $id
     * @param string $name
     * @param null|string $afterCategoryId
     */
    private function createCategory(string $id, string $name, ?string $afterCategoryId): void
    {
        $parentRoot = $this->helper->Category()->getRootCategory();

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
                'cmsPageId' => $this->helper->Cms()->getDefaultCategoryLayout()->getId(),
                'parentId' => $parentRoot->getId(),
                'afterCategoryId' => $afterCategoryId,
            ],
        ], Context::createDefaultContext());
    }
}
