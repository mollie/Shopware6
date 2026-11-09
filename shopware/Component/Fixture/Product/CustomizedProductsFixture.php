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
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Product with a Custom Products template (SwagCustomizedProducts), shaped like a real merchant case:
 * an engraving text field and a packaging select, both without surcharge.
 * Skipped when the plugin is not installed.
 */
final class CustomizedProductsFixture extends AbstractFixture
{
    use ProductTrait;
    use SalesChannelTrait;

    public const PRODUCT_NUMBER = 'MOL_CUSTOMIZED_PRODUCT';
    public const TEMPLATE_ID = 'mollie-customized-products-template';
    public const OPTION_ENGRAVING_ID = 'mollie-customized-products-engraving';
    public const OPTION_PACKAGING_ID = 'mollie-customized-products-packaging';
    public const VALUE_PAPER_ID = 'mollie-customized-products-packaging-paper';

    private const TEMPLATE_REPOSITORY = 'swag_customized_products_template.repository';
    private const VALUE_BOX_ID = 'mollie-customized-products-packaging-box';

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
        if (! $this->container->has(self::TEMPLATE_REPOSITORY)) {
            return;
        }

        /** @var EntityRepository $templateRepository */
        $templateRepository = $this->container->get(self::TEMPLATE_REPOSITORY);
        $templateRepository->upsert([$this->getTemplateData()], $context);

        $image = 'tshirt-black.png';
        $mediaIds = $this->getMediaMapping([$image], $context);
        $description = 'Mollie Product with a Custom Products configuration for testing purpose in development environment.';

        $productData = $this->getProductData('Pizzaschere 2970 "Speciale" mit Namensgravur', self::PRODUCT_NUMBER, $description, $mediaIds[$image], MollieCategoriesFixture::CATEGORY_REGULAR, $this->getSalesChannelId($context), 41.96);
        $productData['swagCustomizedProductsTemplateId'] = Uuid::fromStringToHex(self::TEMPLATE_ID);

        $this->productRepository->upsert([$productData], $context);
    }

    public function uninstall(Context $context): void
    {
        if (! $this->container->has(self::TEMPLATE_REPOSITORY)) {
            return;
        }

        $this->productRepository->delete([['id' => $this->getProductId(self::PRODUCT_NUMBER)]], $context);

        /** @var EntityRepository $templateRepository */
        $templateRepository = $this->container->get(self::TEMPLATE_REPOSITORY);
        $templateRepository->delete([['id' => Uuid::fromStringToHex(self::TEMPLATE_ID)]], $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function getTemplateData(): array
    {
        return [
            'id' => Uuid::fromStringToHex(self::TEMPLATE_ID),
            'internalName' => 'Mollie Gravur',
            'displayName' => 'Produkt Konfiguration',
            'active' => true,
            'stepByStep' => false,
            'confirmInput' => false,
            'optionsAutoCollapse' => false,
            'options' => [
                [
                    'id' => Uuid::fromStringToHex(self::OPTION_ENGRAVING_ID),
                    'type' => 'textfield',
                    'displayName' => 'Persönliche Gravur',
                    'typeProperties' => ['minLength' => 1, 'maxLength' => 30],
                    'required' => true,
                    'oneTimeSurcharge' => false,
                    'relativeSurcharge' => false,
                    'advancedSurcharge' => false,
                    'position' => 1,
                    'price' => $this->getZeroPrice(),
                ],
                [
                    'id' => Uuid::fromStringToHex(self::OPTION_PACKAGING_ID),
                    'type' => 'select',
                    'displayName' => 'Verpackung',
                    'typeProperties' => ['isMultiSelect' => false, 'isDropDown' => true],
                    'required' => true,
                    'oneTimeSurcharge' => false,
                    'relativeSurcharge' => false,
                    'advancedSurcharge' => false,
                    'position' => 2,
                    'price' => $this->getZeroPrice(),
                    'values' => [
                        $this->getSelectValueData(self::VALUE_PAPER_ID, 'Einzeln verpackt in Papier', 1, true),
                        $this->getSelectValueData(self::VALUE_BOX_ID, 'Geschenkbox', 2, false),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getSelectValueData(string $key, string $displayName, int $position, bool $default): array
    {
        return [
            'id' => Uuid::fromStringToHex($key),
            'value' => ['_value' => ''],
            'displayName' => $displayName,
            'default' => $default,
            'oneTimeSurcharge' => false,
            'relativeSurcharge' => false,
            'advancedSurcharge' => false,
            'position' => $position,
            'price' => $this->getZeroPrice(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getZeroPrice(): array
    {
        return [
            [
                'currencyId' => Defaults::CURRENCY,
                'gross' => 0.0,
                'net' => 0.0,
                'linked' => true,
            ],
        ];
    }
}
