<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Fixture\Product;

use Mollie\Shopware\Component\Fixture\AbstractFixture;
use Mollie\Shopware\Component\Fixture\FixtureGroup;
use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Media\File\FileFetcher;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class RegularProductsFixture extends AbstractFixture
{
    use ProductTrait;

    public function __construct(
        private FileFetcher $fileFetcher,
        private MediaService $mediaService,
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
        $this->getMediaId('tshirt-black.png');
    }

    public function uninstall(Context $context): void
    {
        // TODO: Implement uninstall() method.
    }

}