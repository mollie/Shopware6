<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\ApplePayDirect\Services;

/**
 * Responsible for sanitizing the validation URL for Apple Pay
 */
class ApplePayValidationUrlSanitizer
{
    /**
     * Sanitize the given validation URL
     *
     * @param string $url
     * @return string
     */
    public function sanitizeValidationUrl(string $url): string
    {
        if (strpos($url, 'http') !== 0) {
            $url = 'https://' . $url;
        }

        if (substr($url, -1) !== '/') {
            $url .= '/';
        }

        return $url;
    }
}
