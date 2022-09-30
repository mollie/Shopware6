<?php


namespace MolliePayments\Fixtures\Category;


use Basecom\FixturePlugin\Fixture;
use Basecom\FixturePlugin\FixtureBag;
use Basecom\FixturePlugin\FixtureHelper;
use Shopware\Core\Content\Category\CategoryEntity;
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
        $catElectronics = $this->helper->Category()->getByName('Free time & electronics');
        $afterCatId = ($catElectronics instanceof CategoryEntity) ? $catElectronics->getId() : null;

        $this->createCategory('0d8eefdd6d12456335280e2ff42431b9', "Voucher", $afterCatId);
        $this->createCategory('0d9eefdd6d12456335280e2ff42431b2', "Subscriptions", $afterCatId);
        $this->createCategory('0d9eefdd6d12456335280e2ff42431b9', "Failures", $afterCatId);
    }

    /**
     * @param string $id
     * @param string $name
     * @param string|null $afterCategoryId
     */
    private function createCategory(string $id, string $name, ?string $afterCategoryId): void
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
                'afterCategoryId' => $afterCategoryId,
            ],
        ], Context::createDefaultContext());
    }

}
