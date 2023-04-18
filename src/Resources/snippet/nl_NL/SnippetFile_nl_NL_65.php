<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Resources\snippet\nl_NL;

use Shopware\Core\System\Snippet\Files\AbstractSnippetFile;

class SnippetFile_nl_NL_65 extends AbstractSnippetFile
{
    public function getName(): string
    {
        return 'mollie-payments.nl-NL';
    }

    public function getTechnicalName(): string
    {
        return $this->getName();
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
        return 'Mollie B.V.';
    }

    public function isBase(): bool
    {
        return false;
    }
}
