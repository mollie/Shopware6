<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;

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
            $url = $this->encodePathAndQuery($url);
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

    private function encodePathAndQuery(string $fullUrl):string
    {
        $urlParts = parse_url($fullUrl);

        $scheme = isset($urlParts['scheme']) ? $urlParts['scheme'] . '://' : '';

        $host = isset($urlParts['host']) ? $urlParts['host'] : '';

        $port = isset($urlParts['port']) ? ':' . $urlParts['port'] : '';

        $user = isset($urlParts['user']) ? $urlParts['user'] : '';

        $pass = isset($urlParts['pass']) ? ':' . $urlParts['pass']  : '';

        $pass = ($user || $pass) ? "$pass@" : '';

        $path = isset($urlParts['path']) ? $urlParts['path'] : '';

        if (mb_strlen($path) > 0) {
            $pathParts = explode('/', $path);
            array_walk($pathParts, function (&$pathPart) {
                $pathPart = rawurlencode($pathPart);
            });
            $path = implode('/', $pathParts);
        }

        $query = isset($urlParts['query']) ? '?' . $urlParts['query'] : '';


        $fragment = isset($urlParts['fragment']) ? '#' . $urlParts['fragment'] : '';

        return trim($scheme.$user.$pass.$host.$port.$path.$query.$fragment);
    }
}
