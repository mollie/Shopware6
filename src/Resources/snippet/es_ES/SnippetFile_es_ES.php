<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Resources\snippet\es_ES;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class SnippetFile_es_ES implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'mollie-payments.es-ES';
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
        return __DIR__ . '/mollie-payments.es-ES.json';
    }

    public function getIso(): string
    {
        return 'es-ES';
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
