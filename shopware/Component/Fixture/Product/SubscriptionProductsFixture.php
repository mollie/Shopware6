<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Fixture\Product;

use Mollie\Shopware\Component\Fixture\AbstractFixture;
use Mollie\Shopware\Component\Fixture\Category\MollieCategoriesFixture;
use Mollie\Shopware\Component\Fixture\FixtureGroup;
use Mollie\Shopware\Component\Fixture\SalesChannelTrait;
use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Media\File\FileFetcher;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SubscriptionProductsFixture extends AbstractFixture
{
    use ProductTrait;
    use SalesChannelTrait;

    /**
     * @param EntityRepository<ProductCollection<ProductEntity>> $productRepository
     * @param EntityRepository<PropertyGroupCollection<PropertyGroupEntity>> $propertyGroupRepository
     */
    public function __construct(
        private FileFetcher $fileFetcher,
        private MediaService $mediaService,
        #[Autowire(service: 'product.repository')]
        private readonly EntityRepository $productRepository,
        #[Autowire(service: 'property_group.repository')]
        private readonly EntityRepository $propertyGroupRepository,
        #[Autowire(service: 'service_container')]
        private readonly ContainerInterface $container
    ) {
    }

    public function getGroup(): FixtureGroup
    {
        return FixtureGroup::DATA;
    }

    public function install(Context $context): void
    {
        $image = 'champagne.png';
        $mediaIds = $this->getMediaMapping([$image], $context);
        $salesChannelId = $this->getSalesChannelId($context);

        $mediaId = $mediaIds[$image];
        $category = MollieCategoriesFixture::CATEGORY_SUBSCRIPTIONS;
        $description = 'Mollie Subscription Product for testing purpose in development environment.';

        $customFieldsDaily = [
            'mollie_payments_product_subscription_enabled' => true,
            'mollie_payments_product_subscription_interval' => 1,
            'mollie_payments_product_subscription_interval_unit' => 'days'
        ];

        $customFieldsWeekly = [
            'mollie_payments_product_subscription_enabled' => true,
            'mollie_payments_product_subscription_interval' => 1,
            'mollie_payments_product_subscription_interval_unit' => 'weeks'
        ];
        $customFieldsMonthly = [
            'mollie_payments_product_subscription_enabled' => true,
            'mollie_payments_product_subscription_interval' => 1,
            'mollie_payments_product_subscription_interval_unit' => 'months'
        ];

        $customFieldsThreeWeekly = [
            'mollie_payments_product_subscription_enabled' => true,
            'mollie_payments_product_subscription_interval' => 3,
            'mollie_payments_product_subscription_interval_unit' => 'weeks'
        ];
        $customFieldsDailyRepetition = [
            'mollie_payments_product_subscription_enabled' => true,
            'mollie_payments_product_subscription_interval' => 1,
            'mollie_payments_product_subscription_interval_unit' => 'days',
            'mollie_payments_product_subscription_repetition' => '10'
        ];
        $parentId = $this->getProductId('MOL_SUB_6');
        $options = $this->getSubscriptionOptions();

        $productData = [];
        $productData[] = $this->getProductData('Subscription (1x Daily)', 'MOL_SUB_1', $description, $mediaId, $category, $salesChannelId, 19.00, customFields: $customFieldsDaily);
        $productData[] = $this->getProductData('Subscription (1x Weekly)', 'MOL_SUB_2', $description, $mediaId, $category, $salesChannelId, 29.00, customFields: $customFieldsWeekly);
        $productData[] = $this->getProductData('Subscription (3x Weekly)', 'MOL_SUB_3', $description, $mediaId, $category, $salesChannelId, 29.00, customFields: $customFieldsThreeWeekly);
        $productData[] = $this->getProductData('Subscription (1x Monthly)', 'MOL_SUB_4', $description, $mediaId, $category, $salesChannelId, 19.90, customFields: $customFieldsMonthly);
        $productData[] = $this->getProductData('Subscription (1x Daily, 10 Times)', 'MOL_SUB_5', $description, $mediaId, $category, $salesChannelId, 19.90, customFields: $customFieldsDailyRepetition);
        $productData[] = $this->getProductData('Subscription Variant', 'MOL_SUB_6', $description, $mediaId, $category, $salesChannelId, 19.90, options: $options);

        $productData[] = $this->getProductData('Regular Child', 'MOL_SUB_6.1', $description, $mediaId, $category, $salesChannelId, 19.90, parentId: $parentId, options: [['id' => $this->getOptionId('regular')]]);
        $productData[] = $this->getProductData('Daily Child', 'MOL_SUB_6.2', $description, $mediaId, $category, $salesChannelId, 10.99, parentId: $parentId, customFields: $customFieldsDaily, options: [['id' => $this->getOptionId('daily')]]);
        $productData[] = $this->getProductData('Weekly Child', 'MOL_SUB_6.3', $description, $mediaId, $category, $salesChannelId, 8.99, parentId: $parentId, customFields: $customFieldsWeekly, options: [['id' => $this->getOptionId('weekly')]]);
        $productData[] = $this->getProductData('Monthly Child', 'MOL_SUB_6.4', $description, $mediaId, $category, $salesChannelId, 5.99, parentId: $parentId, customFields: $customFieldsMonthly, options: [['id' => $this->getOptionId('monthly')]]);

        $this->productRepository->upsert($productData, $context);
    }

    public function uninstall(Context $context): void
    {
        $productData = [
            ['id' => $this->getProductId('MOL_SUB_1')],
            ['id' => $this->getProductId('MOL_SUB_2')],
            ['id' => $this->getProductId('MOL_SUB_3')],
            ['id' => $this->getProductId('MOL_SUB_4')],
            ['id' => $this->getProductId('MOL_SUB_5')],
            ['id' => $this->getProductId('MOL_SUB_6')],
            ['id' => $this->getProductId('MOL_SUB_6.1')],
            ['id' => $this->getProductId('MOL_SUB_6.2')],
            ['id' => $this->getProductId('MOL_SUB_6.3')],
            ['id' => $this->getProductId('MOL_SUB_6.4')],
        ];
        $this->productRepository->delete($productData, $context);

        $propertyGroups = [
            ['id' => $this->getOptionId('subscriptions')],
        ];

        $this->propertyGroupRepository->delete($propertyGroups, $context);
    }

    /**
     * @return array<mixed>
     */
    private function getSubscriptionOptions(): array
    {
        $subscriptionGroup = [
            'id' => $this->getOptionId('subscriptions'),
            'name' => 'Subscription Interval',
            'filterable' => true,
            'visibleOnProductDetailPage' => true,
            'displayType' => 'text',
            'sortingType' => 'position'
        ];

        return [
            [
                'id' => $this->getOptionId('regular'),
                'name' => 'Regular',
                'position' => 1,
                'group' => $subscriptionGroup
            ],
            [
                'id' => $this->getOptionId('daily'),
                'name' => 'Daily',
                'position' => 2,
                'group' => $subscriptionGroup
            ],
            [
                'id' => $this->getOptionId('weekly'),
                'name' => 'Weekly',
                'position' => 3,
                'group' => $subscriptionGroup
            ],
            [
                'id' => $this->getOptionId('monthly'),
                'name' => 'Monthly',
                'position' => 4,
                'group' => $subscriptionGroup
            ]
        ];
    }

    private function getOptionId(string $optionName): string
    {
        return Uuid::fromStringToHex('mollie-option-' . $optionName);
    }
}
