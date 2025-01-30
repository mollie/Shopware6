<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\TranslationImporter;

use DOMDocument;

interface AppenderInterface
{
    public function append(DOMDocument $config, string $key, string $text, string $languageCode): AppenderResult;
}
