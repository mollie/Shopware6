<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Resources\snippet\pt_PT;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class SnippetFile_pt_PT implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'mollie-payments.pt-PT';
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
        return __DIR__ . '/mollie-payments.pt-PT.json';
    }

    public function getIso(): string
    {
        return 'pt-PT';
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
