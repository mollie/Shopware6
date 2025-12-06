<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Logger;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\IpUtils;

#[AsMonologProcessor(channel: 'mollie')]
final class RecordAnonymizer implements ProcessorInterface
{
    private const URL_SLUG = '/payment/finalize-transaction';

    public function __invoke(LogRecord $record): LogRecord
    {
        $recordArray = $record->toArray();

        /** @var array<mixed> $extraData */
        $extraData = $recordArray['extra'] ?? [];
        if (isset($extraData['ip'])) {
            // replace it with our anonymous IP
            $extraData['ip'] = IpUtils::anonymize(trim((string) $extraData['ip']));
        }

        if (isset($extraData['url'])) {
            $extraData['url'] = $this->anonymize((string) $extraData['url']);
        }
        $record['extra'] = $extraData;

        return $record;
    }

    private function anonymize(string $url): string
    {
        // we do not want to save the used tokens in our log file
        // also, those one time tokens are pretty long. it's just annoying and fills disk space ;)
        if (strpos($url, self::URL_SLUG) !== false) {
            $parts = explode(self::URL_SLUG, $url);

            $url = str_replace($parts[1], '', $url);
        }

        return (string) $url;
    }
}
