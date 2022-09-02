<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

class TokenAnonymizer
{
    const TOKEN_ANONYMIZER_PLACEHOLDER_SYMBOL = '*';

    const TOKEN_ANONYMIZER_PLACEHOLDER_COUNT = 4;

    const TOKEN_ANONYMIZER_COUNT_FIRST_CHARACTERS = 3;

    const TOKEN_ANONYMIZER_COUNT_LAST_CHARACTERS = 4;

    /**
     * @param string $value
     * @return string
     */
    public function anonymize(string $value): string
    {
        $value = trim($value);

        if (empty($value)) {
            return '';
        }

        if (strlen($value) < self::TOKEN_ANONYMIZER_COUNT_FIRST_CHARACTERS + self::TOKEN_ANONYMIZER_COUNT_LAST_CHARACTERS) {
            return $value[0] . $this->getPlaceHolder();
        }

        $firstChars = substr($value, 0, self::TOKEN_ANONYMIZER_COUNT_FIRST_CHARACTERS);
        $lastChars = substr($value, -1 * self::TOKEN_ANONYMIZER_COUNT_LAST_CHARACTERS);

        return sprintf('%s%s%s', $firstChars, $this->getPlaceHolder(), $lastChars);
    }

    /**
     * @return string
     */
    private function getPlaceHolder(): string
    {
        return str_repeat(self::TOKEN_ANONYMIZER_PLACEHOLDER_SYMBOL, self::TOKEN_ANONYMIZER_PLACEHOLDER_COUNT);
    }
}
