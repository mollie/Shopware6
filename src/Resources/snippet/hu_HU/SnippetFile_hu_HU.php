<?php

declare(strict_types=1);

namespace Kiener\MolliePayments\Resources\snippet\hu_HU;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class SnippetFile_hu_HU implements SnippetFileInterface
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
        return 'hu-HU';
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
