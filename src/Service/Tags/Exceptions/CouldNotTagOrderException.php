<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Tags\Exceptions;

class CouldNotTagOrderException extends \Exception
{
    public const SUBSCRIPTION_CODE = 1;

    private function __construct(string $message, int $code)
    {
        parent::__construct($message, $code);
    }

    public static function forSubscription(string $message): self
    {
        return new self($message, self::SUBSCRIPTION_CODE);
    }
}
