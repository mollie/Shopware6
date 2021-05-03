<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Resources\snippet\nl_NL;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class SnippetFile_nl_NL implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'mollie-payments.nl-NL';
    }

    public function getPath(): string
    {
        return __DIR__ . '/mollie-payments.nl-NL.json';
    }

    public function getIso(): string
    {
        return 'nl-NL';
    }

    public function getAuthor(): string
    {
        return 'Carsten Schmitz';
    }

    public function isBase(): bool
    {
        return false;
    }
}
