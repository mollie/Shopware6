<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Service\MollieApi;

use Kiener\MolliePayments\Service\MollieApi\LineItemDataExtractor;
use Kiener\MolliePayments\Service\UrlParsingService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Framework\Uuid\Uuid;

class LineItemDataExtractorTest extends TestCase
{
    public function testWithMissingProduct(): void
    {
        $extractor = new LineItemDataExtractor(new UrlParsingService());
        $lineItemId = Uuid::randomHex();
        $lineItem = new OrderLineItemEntity();
        $lineItem->setId($lineItemId);
        $actual = $extractor->extractExtraData($lineItem);

        self::assertSame($lineItemId, $actual->getSku());
        self::assertNull($actual->getProductUrl());
        self::assertNull($actual->getImageUrl());
    }

    public function testNoMediaNoSeo(): void
    {
        $expected = 'foo';
        $extractor = new LineItemDataExtractor(new UrlParsingService());
        $lineItem = new OrderLineItemEntity();
        $product = new ProductEntity();
        $product->setProductNumber($expected);
        $lineItem->setProduct($product);

        $actual = $extractor->extractExtraData($lineItem);
        self::assertSame($expected, $actual->getSku());
        self::assertNull($actual->getProductUrl());
        self::assertNull($actual->getImageUrl());
    }

    public function testMediaExtraction(): void
    {
        $expectedImageUrl = 'https://bar.baz';
        $expectedProductNumber = 'foo';
        $extractor = new LineItemDataExtractor(new UrlParsingService());
        $lineItem = new OrderLineItemEntity();
        $product = new ProductEntity();
        $product->setProductNumber($expectedProductNumber);
        $media = new MediaEntity();
        $media->setId(Uuid::randomHex());
        $media->setUrl($expectedImageUrl);
        $productMediaEntity = new ProductMediaEntity();
        $productMediaEntity->setId(Uuid::randomHex());
        $productMediaEntity->setMedia($media);
        $mediaCollection = new ProductMediaCollection([$productMediaEntity]);
        $product->setMedia($mediaCollection);
        $lineItem->setProduct($product);

        $actual = $extractor->extractExtraData($lineItem);
        self::assertSame($expectedProductNumber, $actual->getSku());
        self::assertNull($actual->getProductUrl());
        self::assertSame($expectedImageUrl, $actual->getImageUrl());
    }

    public function testSeoUrlExtraction(): void
    {
        $expectedSeoUrl = 'https://bar.foo';
        $expectedProductNumber = 'foo';
        $extractor = new LineItemDataExtractor(new UrlParsingService());
        $lineItem = new OrderLineItemEntity();
        $product = new ProductEntity();
        $product->setProductNumber($expectedProductNumber);
        $seoUrl = new SeoUrlEntity();
        $seoUrl->setId(Uuid::randomHex());
        $seoUrl->setUrl($expectedSeoUrl);
        $seoUrlCollection = new SeoUrlCollection([$seoUrl]);
        $product->setSeoUrls($seoUrlCollection);
        $lineItem->setProduct($product);

        $actual = $extractor->extractExtraData($lineItem);
        self::assertSame($expectedProductNumber, $actual->getSku());
        self::assertSame($expectedSeoUrl, $actual->getProductUrl());
        self::assertNull($actual->getImageUrl());
    }

    public function testCompleteExtraction(): void
    {
        $expectedImageUrl = 'https://bar.baz';
        $expectedSeoUrl = 'https://bar.foo';
        $expectedProductNumber = 'foo';
        $extractor = new LineItemDataExtractor(new UrlParsingService());
        $lineItem = new OrderLineItemEntity();
        $product = new ProductEntity();
        $product->setProductNumber($expectedProductNumber);
        $seoUrl = new SeoUrlEntity();
        $seoUrl->setId(Uuid::randomHex());
        $seoUrl->setUrl($expectedSeoUrl);
        $seoUrlCollection = new SeoUrlCollection([$seoUrl]);
        $product->setSeoUrls($seoUrlCollection);
        $media = new MediaEntity();
        $media->setId(Uuid::randomHex());
        $media->setUrl($expectedImageUrl);
        $productMediaEntity = new ProductMediaEntity();
        $productMediaEntity->setId(Uuid::randomHex());
        $productMediaEntity->setMedia($media);
        $mediaCollection = new ProductMediaCollection([$productMediaEntity]);
        $product->setMedia($mediaCollection);
        $lineItem->setProduct($product);

        $actual = $extractor->extractExtraData($lineItem);
        self::assertSame($expectedProductNumber, $actual->getSku());
        self::assertSame($expectedSeoUrl, $actual->getProductUrl());
        self::assertSame($expectedImageUrl, $actual->getImageUrl());
    }
}
