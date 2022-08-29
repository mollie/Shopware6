<?php

namespace Kiener\MolliePayments\Service\Mail\AttachmentGenerator;

use Shopware\Core\Framework\Context;

class LogFileArchiveGenerator implements GeneratorInterface
{
    /**
     * @var string
     */
    protected $logDirectory;

    /**
     * @var string
     */
    protected $logFilePrefix;


    /**
     * @param string $logDirectory
     * @param string $logFilePrefix
     */
    public function __construct(string $logDirectory, string $logFilePrefix = '')
    {
        $this->logDirectory = $logDirectory;
        $this->logFilePrefix = $logFilePrefix;
    }

    /**
     * @param Context $context
     * @return array<mixed>
     */
    public function generate(Context $context): array
    {
        $filename = $this->logFilePrefix . 'logs.zip';
        $fullPath = $this->logDirectory . DIRECTORY_SEPARATOR . $filename;

        $archive = new \ZipArchive();
        $archive->open($fullPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $archive->addPattern('/^' . $this->logFilePrefix . '.*?\.log$/', $this->logDirectory, [
            'remove_all_path' => true
        ]);
        $archive->close();


        $content = '';
        $mimeType = '';

        # it can be that no log file exists
        # in that case just skip it
        if (file_exists($fullPath)) {
            $content = \file_get_contents($fullPath);
            $mimeType = \mime_content_type($fullPath);
            // Don't leave any evidence.
            \unlink($fullPath);
        }

        return [
            'content' => $content,
            'fileName' => $filename,
            'mimeType' => $mimeType,
        ];
    }
}
