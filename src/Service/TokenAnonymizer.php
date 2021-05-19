<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;


class TokenAnonymizer
{
    const TOKEN_ANONYMIZER_PLACEHOLDER_SYMBOL = '*';

    const TOKEN_ANONYMIZER_PLACEHOLDER_COUNT = 4;

    const TOKEN_ANONYMIZER_MAX_LENGTH = 15;

    public function anonymize(string $value): string
    {
        $value = trim($value);

        if (empty($value)) {
            return '';
        }

        if (strlen($value) < self::TOKEN_ANONYMIZER_PLACEHOLDER_COUNT) {
            return $value[0] . $this->getPlaceHolder();
        }

        # only get the original value up to
        # the allowed max length
        $value = substr($value, 0, self::TOKEN_ANONYMIZER_MAX_LENGTH);

        # replace the last 4 characters with our placeholders
        return substr($value, 0, -4) . $this->getPlaceholder();
    }

    private function getPlaceHolder(): string
    {
        return str_repeat(self::TOKEN_ANONYMIZER_PLACEHOLDER_SYMBOL, self::TOKEN_ANONYMIZER_PLACEHOLDER_COUNT);
    }
}
