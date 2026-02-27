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

final class VoucherProductsFixture extends AbstractFixture
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
        $mediaIds = $this->getMediaMapping(['tshirt-white.png', 'champagne.png'], $context);
        $salesChannelId = $this->getSalesChannelId($context);

        $category = MollieCategoriesFixture::CATEGORY_VOUCHER;

        $description = 'Mollie Voucher Product for testing purpose in development environment.';

        $customFieldsEco = [
            'mollie_payments_product_voucher_type' => 1,
        ];

        $customFieldsMeal = [
            'mollie_payments_product_voucher_type' => 2,
        ];

        $customFieldsGift = [
            'mollie_payments_product_voucher_type' => 3,
        ];
        $customFieldsAll = [
            'mollie_payments_product_voucher_type' => [1, 2, 3],
        ];

        $productData = [];
        $productData[] = $this->getProductData('Voucher ECO', 'MOL_VOUCHER_1', $description, $mediaIds['tshirt-white.png'], $category, $salesChannelId, 19.00, customFields: $customFieldsEco);
        $productData[] = $this->getProductData('Voucher MEAL', 'MOL_VOUCHER_2', $description, $mediaIds['champagne.png'], $category, $salesChannelId, 19.00, customFields: $customFieldsMeal);
        $productData[] = $this->getProductData('Voucher GIFT', 'MOL_VOUCHER_3', $description, $mediaIds['tshirt-white.png'], $category, $salesChannelId, 19.00, customFields: $customFieldsGift);
        $productData[] = $this->getProductData('Voucher ALL', 'MOL_VOUCHER_4', $description, $mediaIds['champagne.png'], $category, $salesChannelId, 19.00, customFields: $customFieldsAll);

        $this->productRepository->upsert($productData, $context);
    }

    public function uninstall(Context $context): void
    {
        $productData = [
            ['id' => $this->getProductId('MOL_VOUCHER_1')],
            ['id' => $this->getProductId('MOL_VOUCHER_2')],
            ['id' => $this->getProductId('MOL_VOUCHER_3')],
            ['id' => $this->getProductId('MOL_VOUCHER_4')],
        ];
        $this->productRepository->delete($productData, $context);
    }
}
