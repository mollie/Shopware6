<?php

declare(strict_types=1);

namespace Kiener\MolliePayments\Resources\snippet\fi_FI;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class SnippetFile_fi_FI implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'mollie-payments.'.$this->getIso();
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
        return __DIR__ . '/'.$this->getName().'.json';
    }

    public function getIso(): string
    {
        return 'fi-FI';
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
