<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

class DomainExtractor
{
    public function getCleanDomain(string $url): string
    {
        // we need to have a protocol before the parse url command
        // in order to have it work correctly
        if (strpos($url, 'http') !== 0) {
            $url = 'https://' . $url;
        }

        // now extract the raw domain without protocol
        // and without any sub shop urls
        return (string) parse_url($url, PHP_URL_HOST);
    }
}
