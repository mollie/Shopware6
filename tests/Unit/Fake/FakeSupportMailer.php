<?php

declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Mollie\Shopware\Component\Support\SupportMailerInterface;
use Shopware\Core\Framework\Context;

final class FakeSupportMailer implements SupportMailerInterface
{
    private ?\Throwable $exception = null;

    /** @var array<int, array<string, mixed>> */
    private array $calls = [];

    public function throwOnSend(\Throwable $exception): void
    {
        $this->exception = $exception;
    }

    public function send(
        string $name,
        string $email,
        ?string $recipientLocale,
        string $host,
        string $subject,
        string $message,
        Context $context
    ): void {
        $this->calls[] = [
            'name' => $name,
            'email' => $email,
            'recipientLocale' => $recipientLocale,
            'host' => $host,
            'subject' => $subject,
            'message' => $message,
        ];

        if ($this->exception !== null) {
            throw $this->exception;
        }
    }

    public function wasCalledOnce(): bool
    {
        return count($this->calls) === 1;
    }

    /**
     * @return null|array<string, mixed>
     */
    public function getLastCall(): ?array
    {
        if ($this->calls === []) {
            return null;
        }

        return end($this->calls);
    }
}
