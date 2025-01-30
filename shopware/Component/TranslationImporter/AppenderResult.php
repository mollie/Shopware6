<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\TranslationImporter;

final class AppenderResult
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_ERROR = 'error';
    public const STATUS_WARNING = 'warning';
    private string $message;
    private string $status;

    public function __construct(string $message,string $status = self::STATUS_SUCCESS)
    {

        $this->message = $message;
        $this->status = $status;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

}