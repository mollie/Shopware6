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
        $fullUrl .= '&width=1920&height={height}';
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

        $query = '';
        if (isset($urlParts['query'])) {
            $urlParts['query'] = $this->sanitizeQuery(explode('&', $urlParts['query']));
            $query = '?' . implode('&', $urlParts['query']);
        }


        $fragment = isset($urlParts['fragment']) ? '#' . $urlParts['fragment'] : '';

        return trim($scheme.$user.$pass.$host.$port.$path.$query.$fragment);
    }

    /**
     * Sanitizes an array of query strings by URL encoding their components.
     *
     * This method takes an array of query strings, where each string is expected to be in the format
     * 'key=value'. It applies the sanitizeQueryPart method to each query string to ensure the keys
     * and values are URL encoded, making them safe for use in URLs.
     *
     * @param array $query An array of query strings to be sanitized.
     * @return array The sanitized array with URL encoded query strings.
     */
    private function sanitizeQuery(array $query): array
    {
        // Use array_map to apply the sanitizeQueryPart method to each element of the $query array
        return array_map([$this, 'sanitizeQueryPart'], $query);
    }

    /**
     * Sanitizes a single query string part by URL encoding its key and value.
     *
     * This method takes a query string part, expected to be in the format 'key=value', splits it into
     * its key and value components, URL encodes each component, and then recombines them into a single
     * query string part.
     *
     * @param string $queryPart A single query string part to be sanitized.
     * @return string The sanitized query string part with URL encoded components.
     */
    private function sanitizeQueryPart(string $queryPart): string
    {
        if (strpos($queryPart, '=') === false) {
            return$queryPart;
        }

        //  Split the query part into key and value based on the '=' delimiter
        [$key, $value] = explode('=', $queryPart);

        $key = rawurlencode($key);
        $value = rawurlencode($value);

        return sprintf('%s=%s', $key, $value);
    }
}
