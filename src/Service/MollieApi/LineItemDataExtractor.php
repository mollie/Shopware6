<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Service\UrlParsingService;
use Kiener\MolliePayments\Struct\LineItemExtraData;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;

class LineItemDataExtractor
{
    /**
     * @var UrlParsingService
     */
    private $urlParsingService;
    public function __construct(UrlParsingService $urlParsingService)
    {
        $this->urlParsingService = $urlParsingService;
    }

    public function extractExtraData(OrderLineItemEntity $lineItem): LineItemExtraData
    {
        $product = $lineItem->getProduct();

        // extra data is not needed for successful orders
        if (!$product instanceof ProductEntity) {
            return new LineItemExtraData($lineItem->getId(), null, null);
        }

        $extraData = new LineItemExtraData($product->getProductNumber(), null, null);

        $medias = $product->getMedia();
        if ($medias instanceof ProductMediaCollection
            && $medias->first() instanceof ProductMediaEntity
            && $medias->first()->getMedia() instanceof MediaEntity
        ) {
            $url = $medias->first()->getMedia()->getUrl();
            $url = $this->urlParsingService->encodePathAndQuery($url);
            $extraData->setImageUrl($url);
        }

        $seoUrls = $product->getSeoUrls();
        if ($seoUrls instanceof SeoUrlCollection
            && $seoUrls->first() instanceof SeoUrlEntity
        ) {
            $extraData->setProductUrl($seoUrls->first()->getUrl());
        }

        return $extraData;
    }
}
