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
        $this->categoryRepository->upsert([
            [
                'id' => '0d8eefdd6d12456335280e2ff42431b9',
                'translations' => [
                    'de-DE' => [
                        'name' => 'Gutscheine'
                    ],
                    'en-GB' => [
                        'name' => 'Voucher',
                    ],
                ],
                'productAssignmentType' => 'product',
                'level' => 2,
                'active' => true,
                'displayNestedProducts' => true,
                'visible' => true,
                'type' => 'page',
                'cmsPageId' => $this->helper->Cms()->getDefaultCategoryLayout()->getId(),
                'afterCategoryId' => null,
                'parentId' => $this->helper->Category()->getByName('Catalogue #1')->getId(),
            ],
        ], Context::createDefaultContext());

    }

}
