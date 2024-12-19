<?php

namespace MolliePayments\Tests\Fakes;

use Symfony\Contracts\Translation\TranslatorInterface;

class FakeTranslator implements TranslatorInterface
{
    /**
     * @var array
     */
    private $snippets;

    /**
     * @param string $key
     * @param string $value
     */
    public function addSnippet(string $key, string $value): void
    {
        $this->snippets[$key] = $value;
    }

    /**
     * @param string $id
     * @param array $parameters
     * @param null $domain
     * @param null $locale
     * @return mixed|string
     */
    public function trans($id, array $parameters = [], $domain = null, $locale = null): string
    {
        return $this->snippets[$id];
    }

    public function getLocale(): string
    {
        // TODO: Implement getLocale() method.
    }
}
