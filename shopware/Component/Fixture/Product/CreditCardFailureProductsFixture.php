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

final class CreditCardFailureProductsFixture extends AbstractFixture
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
        $image = 'tshirt-black-fail.png';
        $mediaIds = $this->getMediaMapping([$image], $context);
        $salesChannelId = $this->getSalesChannelId($context);
        $mediaId = $mediaIds[$image];
        $category = MollieCategoriesFixture::CATEGORY_CREDIT_CARD_ERRORS;
        $description = 'Mollie Product for testing purpose in development environment. Use "failed" on the Mollie Payment Sandbox page to force the special error reason of this product.';

        $productData = [];
        $productData[] = $this->getProductData('Invalid Card Number', 'MOL_ERROR_1', $description, $mediaId, $category, $salesChannelId, 1001.00);
        $productData[] = $this->getProductData('Invalid CVV', 'MOL_ERROR_2', $description, $mediaId, $category, $salesChannelId, 1002.00);
        $productData[] = $this->getProductData('Invalid Card Holder Name', 'MOL_ERROR_3', $description, $mediaId, $category, $salesChannelId, 1003.00);
        $productData[] = $this->getProductData('Card Expired', 'MOL_ERROR_4', $description, $mediaId, $category, $salesChannelId, 1004.00);
        $productData[] = $this->getProductData('Invalid Card Type', 'MOL_ERROR_5', $description, $mediaId, $category, $salesChannelId, 1005.00);
        $productData[] = $this->getProductData('Refused by Issuer', 'MOL_ERROR_6', $description, $mediaId, $category, $salesChannelId, 1006.00);
        $productData[] = $this->getProductData('Insufficient Funds', 'MOL_ERROR_7', $description, $mediaId, $category, $salesChannelId, 1007.00);
        $productData[] = $this->getProductData('Inactive Card', 'MOL_ERROR_8', $description, $mediaId, $category, $salesChannelId, 1008.00);
        $productData[] = $this->getProductData('Possible Fraud', 'MOL_ERROR_9', $description, $mediaId, $category, $salesChannelId, 1009.00);
        $productData[] = $this->getProductData('Authentication Failed', 'MOL_ERROR_10', $description, $mediaId, $category, $salesChannelId, 1010.00);
        $productData[] = $this->getProductData('Card Declined', 'MOL_ERROR_11', $description, $mediaId, $category, $salesChannelId, 1011.00);

        $this->productRepository->upsert($productData, $context);
    }

    public function uninstall(Context $context): void
    {
        $productData = [];
        for ($i = 1; $i <= 11; ++$i) {
            $productData[] = [
                'id' => $this->getProductId('MOL_ERROR_' . $i)
            ];
        }

        $this->productRepository->delete($productData, $context);
    }
}
