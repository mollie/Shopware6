<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Resources\snippet\en_GB;

use Shopware\Core\System\Snippet\Files\AbstractSnippetFile;

class SnippetFile_en_GB_65 extends AbstractSnippetFile
{
    public function getName(): string
    {
        return 'mollie-payments.en-GB';
    }

    public function getTechnicalName(): string
    {
        return $this->getName();
    }

    public function getPath(): string
    {
        return __DIR__ . '/mollie-payments.en-GB.json';
    }

    public function getIso(): string
    {
        return 'en-GB';
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
