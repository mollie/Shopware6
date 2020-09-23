<?php

declare(strict_types=1);

namespace Kiener\MolliePayments\Resources\snippet\de_DE;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class SnippetFile_de_DE implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'mollie.payments.de-DE';
    }

    public function getPath(): string
    {
        return __DIR__.'/mollie.payments.de-DE.json';
    }

    public function getIso(): string
    {
        return 'de-DE';
    }

    public function getAuthor(): string
    {
        return 'Reinder van Bochove';
    }

    public function isBase(): bool
    {
        return false;
    }
}
