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
        $recordArray['extra'] = $extraData;

        if (isset($recordArray['context']) && is_array($recordArray['context'])) {
            $recordArray['context'] = $this->maskPersonalDataInArray($recordArray['context']);
        }

        return new LogRecord(
            datetime: $record->datetime,
            channel: $record->channel,
            level: $record->level,
            message: $record->message,
            context: $recordArray['context'] ?? [],
            extra: $recordArray['extra'] ?? [],
            formatted: $record->formatted,
        );
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

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    private function maskPersonalDataInArray(array $data): array
    {
        $sensitiveFields = ['givenName', 'familyName', 'organizationName', 'email', 'phone', 'streetAndNumber', 'postalCode'];
        $urlFields = ['redirectUrl', 'webhookUrl', 'cancelUrl', 'href', 'checkoutUrl', 'finalizeUrl'];
        $tokenFields = ['applePayPaymentToken'];

        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $value = $this->maskPersonalDataInArray($value);
                continue;
            }

            if (in_array($key, $sensitiveFields, true)) {
                $value = '**';
                continue;
            }

            if (in_array($key, $urlFields, true) && is_string($value)) {
                $value = preg_replace_callback(
                    '/(?:token|_sw_payment_token)=([a-z0-9._\-]+?)(?:\*{2})?(?=[&"]|$)/i',
                    fn ($m) => (strpos($m[0], '_sw_payment_token') !== false ? '_sw_payment_token=' : 'token=') . substr($m[1], 0, 2) . '**',
                    $value
                );
                continue;
            }

            if (in_array($key, $tokenFields, true)) {
                $value = '**';
            }
        }
        unset($value);

        return $data;
    }
}
