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
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class RoundingProductsFixture extends AbstractFixture
{
    use ProductTrait;
    use SalesChannelTrait;

    /**
     * @param EntityRepository<ProductCollection<ProductEntity>> $productRepository
     */
    public function __construct(
        private FileFetcher $fileFetcher,
        private MediaService $mediaService,
        #[Autowire(service: 'product.repository')]
        private readonly EntityRepository $productRepository,
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
        $image = 'tshirt-white.png';
        $mediaIds = $this->getMediaMapping([$image], $context);
        $salesChannelId = $this->getSalesChannelId($context);
        $mediaId = $mediaIds[$image];
        $category = MollieCategoriesFixture::CATEGORY_ROUNDING;
        $description = 'Product to test rounding issues.';

        $productData = [];
        $productData[] = $this->getProductData('Product A 4 Decimals', 'MOL_ROUNDING_1', $description, $mediaId, $category, $salesChannelId, 2.7336);
        $productData[] = $this->getProductData('Product B 4 Decimals', 'MOL_ROUNDING_2', $description, $mediaId, $category, $salesChannelId, 2.9334);
        $productData[] = $this->getProductData('Product C 4 Decimals', 'MOL_ROUNDING_3', $description, $mediaId, $category, $salesChannelId, 1.6494);

        $this->productRepository->upsert($productData, $context);
    }

    public function uninstall(Context $context): void
    {
        $productData = [
            ['id' => $this->getProductId('MOL_ROUNDING_1')],
            ['id' => $this->getProductId('MOL_ROUNDING_2')],
            ['id' => $this->getProductId('MOL_ROUNDING_3')],
        ];
        $this->productRepository->delete($productData, $context);
    }
}
