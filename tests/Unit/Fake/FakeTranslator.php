<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Symfony\Contracts\Translation\TranslatorInterface;

final class FakeTranslator implements TranslatorInterface
{
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        if (count($parameters) === 0) {
            return $id;
        }

        return $id . '(' . http_build_query($parameters, '', ',') . ')';
    }

    public function getLocale(): string
    {
        return 'en-GB';
    }
}
