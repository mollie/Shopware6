<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\CancelManager;

/**
 * @final
 */
class CancelItemResponse implements \JsonSerializable
{
    private string $message;
    private bool $success = true;

    /**
     * @var array<mixed>
     */
    private array $data = [];

    public function isSuccessful(): bool
    {
        return $this->success === true;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }


    /**
     * @return false|string
     */
    public function jsonSerialize(): mixed
    {
        return json_encode(['success' => $this->success, 'message' => $this->message]);
    }

    public function failedWithMessage(string $message): self
    {
        $clone = clone $this;
        $clone->success = false;
        $clone->message = $message;
        return $clone;
    }

    /**
     * @param array<mixed> $data
     * @return $this
     */
    public function withData(array $data): self
    {
        $clone = clone $this;
        $clone->data = $data;
        return $clone;
    }

    /**
     * @return array<mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }
}
