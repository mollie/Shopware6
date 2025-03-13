<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Logger;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\IpUtils;

final class RecordAnonymizer implements ProcessorInterface
{
    private const URL_SLUG = '/payment/finalize-transaction';
    /**
     * @param string $url
     * @return string
     */
    private function anonymize(string $url): string
    {
        # we do not want to save the used tokens in our log file
        # also, those one time tokens are pretty long. it's just annoying and fills disk space ;)
        if (strpos($url, self::URL_SLUG) !== false) {
            $parts = explode(self::URL_SLUG, $url);

            $url = str_replace($parts[1], '', $url);
        }

        return (string)$url;
    }
    /**
     * We need to define types here because shopware 6.4 uses old monologger where LogRecord does not exists.
     * @param array|LogRecord $record
     * @return array|LogRecord
     */
    public function __invoke($record)
    {
        /** @phpstan-ignore-next-line */
        if (isset($record['extra'])) {
            if (array_key_exists('ip', $record['extra'])) {
                # replace it with our anonymous IP
                $record['extra']['ip'] = IpUtils::anonymize(trim($record['extra']['ip']));
            }

            if (array_key_exists('url', $record['extra'])) {
                $record['extra']['url'] = $this->anonymize($record['extra']['url']);
            }
        }
        return $record;
    }
}
