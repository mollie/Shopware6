<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\ApplePayDirect\Services;

/**
 * This class is responsible for sanitizing the given domain
 */
class ApplePayDirectDomainSanitizer
{
    /**
     * Sanitize the given domain
     */
    public function sanitizeDomain(string $domain): string
    {
        // we need to have a protocol before the parse url command
        // in order to have it work correctly
        if (strpos($domain, 'http') !== 0) {
            $domain = 'https://' . $domain;
        }

        // now extract the raw domain without protocol
        // and without any sub shop urls
        return (string) parse_url($domain, PHP_URL_HOST);
    }
}
