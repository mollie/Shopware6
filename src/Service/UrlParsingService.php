<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

class UrlParsingService
{
    /**
     * Checks if a given string is a valid URL.
     *
     * @param string $value The string to be checked.
     * @return bool True if the string is a valid URL, false otherwise.
     */
    public function isUrl(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Parses the tracking code from a given URL.
     *
     * This method searches for tracking codes in the URL in the following formats:
     * - As a query parameter (e.g., ?code=12345)
     * - As a path segment (e.g., /code/12345/)
     * - As a hash fragment (e.g., #code=12345)
     *
     * @param string $value The URL to be parsed.
     * @return array{0: string, 1: string} An array where:
     *               - Index 0 contains the parsed tracking code (if found), or an empty string if no code is found.
     *               - Index 1 contains the original URL.
     */
    public function parseTrackingCodeFromUrl(string $value): array
    {
        // Case 1: Query parameter
        if ((bool)preg_match('#(code|shipment|track|tracking)=([a-zA-Z0-9]+)#i', $value, $matches)) {
            return [$matches[2], $value];
        }

        // Case 2: Path-based tracking
        if ((bool)preg_match('#/(code|shipment|track|tracking)/([a-zA-Z0-9]+)/#i', $value, $matches)) {
            return [$matches[2], $value];
        }

        // Case 3: Hash-based tracking
        if ((bool)preg_match('#\#(code|shipment|track|tracking)=([a-zA-Z0-9]+)#i', $value, $matches)) {
            return [$matches[2], $value];
        }

        // could not determine code
        return ['', $value];
    }

    public function encodePathAndQuery(string $fullUrl):string
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

        $query = '';
        if (isset($urlParts['query'])) {
            $urlParts['query'] = $this->sanitizeQuery(explode('&', $urlParts['query']));
            $query = '?' . implode('&', $urlParts['query']);
        }


        $fragment = isset($urlParts['fragment']) ? '#' . rawurlencode($urlParts['fragment']) : '';

        return trim($scheme.$user.$pass.$host.$port.$path.$query.$fragment);
    }

    /**
     * Sanitizes an array of query strings by URL encoding their components.
     *
     * This method takes an array of query strings, where each string is expected to be in the format
     * 'key=value'. It applies the sanitizeQueryPart method to each query string to ensure the keys
     * and values are URL encoded, making them safe for use in URLs.
     *
     * @param string[] $query An array of query strings to be sanitized.
     * @return string[] The sanitized array with URL encoded query strings.
     */
    public function sanitizeQuery(array $query): array
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
    public function sanitizeQueryPart(string $queryPart): string
    {
        if (strpos($queryPart, '=') === false) {
            return $queryPart;
        }

        //  Split the query part into key and value based on the '=' delimiter
        [$key, $value] = explode('=', $queryPart);

        $key = rawurlencode($key);
        $value = rawurlencode($value);

        return sprintf('%s=%s', $key, $value);
    }
}
