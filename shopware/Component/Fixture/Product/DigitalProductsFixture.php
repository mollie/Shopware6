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
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DigitalProductsFixture extends AbstractFixture
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
        $category = MollieCategoriesFixture::CATEGORY_DIGITAL;
        $description = 'Mollie digital download product for testing purpose in development environment. Digital products have no delivery, so no shipping address is required.';

        $productData = $this->getProductData('Digital Mollie Download', 'MOL_DIGITAL', $description, $mediaId, $category, $salesChannelId, 9.90);
        $productData['downloads'] = [
            [
                'id' => Uuid::fromStringToHex('download-' . $productData['id']),
                'mediaId' => $mediaId,
                'position' => 0,
            ],
        ];

        $this->productRepository->upsert([$productData], $context);
    }

    public function uninstall(Context $context): void
    {
        $productData = [
            ['id' => $this->getProductId('MOL_DIGITAL')],
        ];
        $this->productRepository->delete($productData, $context);
    }
}
