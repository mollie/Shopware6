<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Resources\snippet\en_GB;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class SnippetFile_en_GB implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'mollie.payments.en-GB';
    }

    public function getPath(): string
    {
        return __DIR__ . '/mollie.payments.en-GB.json';
    }

    public function getIso(): string
    {
        return 'en-GB';
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