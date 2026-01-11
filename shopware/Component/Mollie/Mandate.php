<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;

final class Mandate implements \JsonSerializable
{
    use JsonSerializableTrait;

    /**
     * @param array<mixed> $details
     */
    public function __construct(private string $id, private PaymentMethod $method, private array $details)
    {
    }

    /**
     * @param array<mixed> $body
     */
    public static function fromClientResponse(array $body): self
    {
        return new self($body['id'],PaymentMethod::from($body['method']),$body['details']);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getMethod(): PaymentMethod
    {
        return $this->method;
    }

    /**
     * @return mixed[]
     */
    public function getDetails(): array
    {
        return $this->details;
    }
}
