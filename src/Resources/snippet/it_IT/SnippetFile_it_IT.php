<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Resources\snippet\it_IT;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class SnippetFile_it_IT implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'mollie-payments.it-IT';
    }

    /**
     * Required for deprecation warnings in Shopware 6.4.17.0
     */
    public function getTechnicalName(): string
    {
        return $this->getName();
    }

    public function getPath(): string
    {
        return __DIR__ . '/mollie-payments.it-IT.json';
    }

    public function getIso(): string
    {
        return 'it-IT';
    }

    public function getAuthor(): string
    {
        return 'Mollie B.V.';
    }

    public function isBase(): bool
    {
        return false;
    }
}
