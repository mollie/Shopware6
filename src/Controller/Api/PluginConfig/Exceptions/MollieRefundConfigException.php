<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\PluginConfig\Exceptions;

class MollieRefundConfigException extends \Exception
{
    public const CODE = 1000;

    final protected function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, self::CODE, $previous);
    }

    public static function fromException(\Throwable $exception): self
    {
        return new self($exception->getMessage(), $exception);
    }
}
