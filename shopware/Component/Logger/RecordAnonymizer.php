<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Logger;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\IpUtils;

#[AsMonologProcessor(channel: 'mollie', priority: -100)]
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
            $extraData['ip'] = $this->anonymizeIp((string) $extraData['ip']);
        }

        if (isset($extraData['url'])) {
            $extraData['url'] = $this->anonymize((string) $extraData['url']);
        }
        $recordArray['extra'] = $extraData;

        if (isset($recordArray['context']) && is_array($recordArray['context'])) {
            $recordArray['context'] = $this->maskPersonalDataInArray($recordArray['context']);
        }

        return new LogRecord(
            $record->datetime,
            $record->channel,
            $record->level,
            $record->message,
            $recordArray['context'],
            $recordArray['extra'],
            $record->formatted,
        );
    }

    private function anonymizeIp(string $ip): string
    {
        // IpUtils masks the host part to 0 (e.g. 1.2.3.0), which customers
        // mistake for a real IP. Replace the masked part with * to make it obvious.
        $anonymized = IpUtils::anonymize(trim($ip));

        if (strpos($anonymized, '.') !== false) {
            return (string) preg_replace('/\.\d+$/', '.*', $anonymized);
        }

        return (string) preg_replace('/::$/', ':*', $anonymized);
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
        $sensitiveFields = ['givenName', 'familyName', 'organizationName', 'email', 'phone', 'streetAndNumber', 'postalCode', 'cardHolder'];
        $urlFields = ['redirectUrl', 'webhookUrl', 'cancelUrl', 'href', 'checkoutUrl', 'finalizeUrl'];
        $tokenFields = ['applePayPaymentToken', 'cardToken'];

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
