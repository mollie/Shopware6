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

final class RegularProductsFixture extends AbstractFixture
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
        $image = 'tshirt-black.png';
        $mediaIds = $this->getMediaMapping([$image], $context);
        $salesChannelId = $this->getSalesChannelId($context);
        $mediaId = $mediaIds[$image];
        $category = MollieCategoriesFixture::CATEGORY_REGULAR;
        $description = 'Mollie Product for testing purpose in development environment. You can use this products for LIVE tests or other scenarios';

        $productData = [];
        $productData[] = $this->getProductData('Cheap Mollie Shirt', 'MOL_CHEAP', $description, $mediaId, $category, $salesChannelId, 1);
        $productData[] = $this->getProductData('Regular Mollie Shirt', 'MOL_REGULAR', $description, $mediaId, $category, $salesChannelId, 29.90);
        $productData[] = $this->getProductData('Reduced Tax Rate Mollie Shirt', 'MOL_REDUCED_TAX', $description, $mediaId, $category, $salesChannelId, 19.90, 7.00);
        $productData[] = $this->getProductData('Tax Free Mollie Shirt', 'MOL_TAX_FREE', $description, $mediaId, $category, $salesChannelId, 19.90, 0.00);

        $this->productRepository->upsert($productData, $context);
    }

    public function uninstall(Context $context): void
    {
        $productData = [
            ['id' => $this->getProductId('MOL_CHEAP')],
            ['id' => $this->getProductId('MOL_REGULAR')],
            ['id' => $this->getProductId('MOL_REDUCED_TAX')],
            ['id' => $this->getProductId('MOL_TAX_FREE')],
        ];
        $this->productRepository->delete($productData, $context);
    }
}
