<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\CancelManager;

/**
 * @final
 */
class CancelItemResponse implements \JsonSerializable
{
    private $message;
    private $success = true;

    private array $data = [];

    public function isSuccessful(): bool
    {
        return $this->success === true;
    }

    /**
     * @return mixed
     */
    public function getMessage()
    {
        return $this->message;
    }


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

    public function withData(array $data): self
    {
        $clone = clone $this;
        $clone->data = $data;
        return $clone;
    }

    public function getData():array
    {
        return $this->data;
    }

}