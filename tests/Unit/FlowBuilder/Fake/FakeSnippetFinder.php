<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\FlowBuilder\Fake;

use Shopware\Administration\Snippet\SnippetFinderInterface;

final class FakeSnippetFinder implements SnippetFinderInterface
{
    /** @var list<string> */
    private array $requestedLocales = [];

    /**
     * @param array<string, mixed> $snippets the admin snippet tree, as the real finder returns it
     *                                       for the requested locale
     */
    public function __construct(private readonly array $snippets = [])
    {
    }

    public function findSnippets(string $locale): array
    {
        $this->requestedLocales[] = $locale;

        return $this->snippets;
    }

    /**
     * @return list<string>
     */
    public function getRequestedLocales(): array
    {
        return $this->requestedLocales;
    }
}
