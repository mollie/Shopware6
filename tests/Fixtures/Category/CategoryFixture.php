<?php


namespace MolliePayments\Fixtures\Category;


use Basecom\FixturePlugin\Fixture;
use Basecom\FixturePlugin\FixtureBag;
use Basecom\FixturePlugin\FixtureHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;


class CategoryFixture extends Fixture
{

    /**
     * @var FixtureHelper
     */
    private $helper;

    /**
     * @var EntityRepositoryInterface
     */
    private $categoryRepository;


    /**
     * @param FixtureHelper $helper
     * @param EntityRepositoryInterface $categoryRepository
     */
    public function __construct(FixtureHelper $helper, EntityRepositoryInterface $categoryRepository)
    {
        $this->helper = $helper;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * @param FixtureBag $bag
     * @return void
     */
    public function load(FixtureBag $bag): void
    {
        $this->createCategory('0d8eefdd6d12456335280e2ff42431b9', "Voucher");
        $this->createCategory('0d9eefdd6d12456335280e2ff42431b9', "Testing Failures");
    }

    /**
     * @param string $id
     * @param string $name
     * @return void
     */
    private function createCategory(string $id, string $name): void
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
                'cmsPageId' => $this->helper->Cms()->getDefaultCategoryLayout()->getId(),
                'parentId' => $this->helper->Category()->getByName('Catalogue #1')->getId(),
                'afterCategoryId' => null,
            ],
        ], Context::createDefaultContext());
    }

}
