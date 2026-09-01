<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Context;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueInterface;

/**
 * For the places that are typed against Shopware's AbstractTranslator instead of the Symfony
 * interface - {@see FakeTranslator} covers the latter. Answers with the snippet key itself, so an
 * assertion shows which snippet the code asked for instead of a hardcoded text.
 *
 * Every concrete method of AbstractTranslator delegates to getDecorated(), so all of them are
 * overridden here: a fake that both returns itself from getDecorated() and inherits those methods
 * recurses until the process dies.
 */
final class FakeShopwareTranslator extends AbstractTranslator
{
    /** @var list<string> */
    private array $requestedSnippets = [];

    /** @var list<string> */
    private array $injectedLocales = [];

    private string $locale = 'en-GB';

    /**
     * @param array<string, string> $translations snippets that answer with the given text instead
     *                                            of the key - use '' for a snippet a shop has not
     *                                            translated
     */
    public function __construct(private readonly array $translations = [])
    {
    }

    public function getDecorated(): AbstractTranslator
    {
        throw new \RuntimeException('FakeShopwareTranslator is not decorated.');
    }

    public function injectSettings(string $salesChannelId, string $languageId, string $locale, Context $context): void
    {
        $this->injectedLocales[] = $locale;
    }

    /**
     * The locales the code under test switched the translator to before asking for a snippet.
     *
     * @return list<string>
     */
    public function getInjectedLocales(): array
    {
        return $this->injectedLocales;
    }

    public function resetInjection(): void
    {
    }

    public function reset(): void
    {
    }

    /**
     * @param string $cacheDir
     */
    public function warmUp($cacheDir): void
    {
    }

    public function getSnippetSetId(?string $locale = null): ?string
    {
        return null;
    }

    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        $this->requestedSnippets[] = $id;

        return $this->translations[$id] ?? $id;
    }

    /**
     * @return list<string>
     */
    public function getRequestedSnippets(): array
    {
        return $this->requestedSnippets;
    }

    public function trace(string $key, \Closure $param)
    {
        return $param();
    }

    public function getTrace(string $key): array
    {
        return [];
    }

    public function getCatalogue(?string $locale = null): MessageCatalogueInterface
    {
        return new MessageCatalogue($locale ?? $this->locale);
    }

    public function getCatalogues(): array
    {
        return [$this->getCatalogue()];
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
