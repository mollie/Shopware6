<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

class UrlParsingService
{
    /**
     * Checks if a given string is a valid URL.
     *
     * @param string $value the string to be checked
     *
     * @return bool true if the string is a valid URL, false otherwise
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
     * @param string $value the URL to be parsed
     *
     * @return array{0: string, 1: string} An array where:
     *                                     - Index 0 contains the parsed tracking code (if found), or an empty string if no code is found.
     *                                     - Index 1 contains the original URL.
     */
    public function parseTrackingCodeFromUrl(string $value): array
    {
        $urlQuery = parse_url($value, PHP_URL_QUERY);
        if ($urlQuery === null) {
            $urlQuery = parse_url($value, PHP_URL_FRAGMENT);
        }
        if ($urlQuery === null) {
            return [$value, ''];
        }
        $urlQuery = (string) $urlQuery;
        $urlWithoutQuery = str_replace($urlQuery, '', $value);

        return [$urlQuery, $urlWithoutQuery . '%s'];
    }

    public function encodePathAndQuery(string $fullUrl): string
    {
        $urlParts = parse_url($fullUrl);

        $scheme = isset($urlParts['scheme']) ? $urlParts['scheme'] . '://' : '';

        $host = $urlParts['host'] ?? '';

        $port = isset($urlParts['port']) ? ':' . $urlParts['port'] : '';

        $user = $urlParts['user'] ?? '';

        $pass = isset($urlParts['pass']) ? ':' . $urlParts['pass'] : '';

        $pass = ($user || $pass) ? "{$pass}@" : '';

        $path = $urlParts['path'] ?? '';

        if (mb_strlen($path) > 0) {
            $pathParts = explode('/', $path);
            $newPathParts = [];
            foreach ($pathParts as $pathPart) {
                $newPathParts[] = rawurlencode($pathPart);
            }

            $path = implode('/', $newPathParts);
        }

        $query = '';
        if (isset($urlParts['query'])) {
            $urlParts['query'] = $this->sanitizeQuery(explode('&', $urlParts['query']));
            $query = '?' . implode('&', $urlParts['query']);
        }

        $fragment = isset($urlParts['fragment']) ? '#' . rawurlencode($urlParts['fragment']) : '';

        return trim($scheme . $user . $pass . $host . $port . $path . $query . $fragment);
    }

    /**
     * Sanitizes an array of query strings by URL encoding their components.
     *
     * This method takes an array of query strings, where each string is expected to be in the format
     * 'key=value'. It applies the sanitizeQueryPart method to each query string to ensure the keys
     * and values are URL encoded, making them safe for use in URLs.
     *
     * @param string[] $query an array of query strings to be sanitized
     *
     * @return string[] the sanitized array with URL encoded query strings
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
     * @param string $queryPart a single query string part to be sanitized
     *
     * @return string the sanitized query string part with URL encoded components
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
