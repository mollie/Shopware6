<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Support\Attachment\Generator;

use Mollie\Shopware\Component\Support\Attachment\Attachment;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class LogArchiveGenerator implements AttachmentGeneratorInterface
{
    public function __construct(
        #[Autowire(value: '%kernel.logs_dir%')]
        private readonly string $logDirectory,
        #[Autowire(value: 'mollie_')]
        private readonly string $logFilePrefix,
    ) {
    }

    public function generate(Context $context): Attachment
    {
        $fileName = $this->logFilePrefix . 'logs.zip';
        $fullPath = $this->logDirectory . DIRECTORY_SEPARATOR . $fileName;

        $archive = new \ZipArchive();
        $archive->open($fullPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $archive->addPattern('/^' . $this->logFilePrefix . '.*?\.log$/', $this->logDirectory, [
            'remove_all_path' => true,
        ]);

        $mollieSubDir = $this->logDirectory . DIRECTORY_SEPARATOR . 'mollie';
        if (is_dir($mollieSubDir)) {
            $archive->addPattern('/^.*?\.log$/', $mollieSubDir, [
                'add_path' => 'mollie/',
                'remove_all_path' => true,
            ]);
        }

        $archive->close();

        $content = '';
        $mimeType = '';

        if (file_exists($fullPath)) {
            $content = (string) file_get_contents($fullPath);
            $mimeType = (string) mime_content_type($fullPath);
            unlink($fullPath);
        }

        return new Attachment($content, $fileName, $mimeType);
    }
}
