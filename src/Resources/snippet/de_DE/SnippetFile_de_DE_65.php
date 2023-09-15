<?php

declare(strict_types=1);

namespace Kiener\MolliePayments\Resources\snippet\de_DE;

use Shopware\Core\System\Snippet\Files\AbstractSnippetFile;

class SnippetFile_de_DE_65 extends AbstractSnippetFile
{

    public function getName(): string
    {
        return 'mollie-payments.de-DE';
    }

    public function getTechnicalName(): string
    {
        return $this->getName();
    }

    public function getPath(): string
    {
        return __DIR__ . '/mollie-payments.de-DE.json';
    }

    public function getIso(): string
    {
        return 'de-DE';
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
