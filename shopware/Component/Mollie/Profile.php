<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;

final class Profile implements \JsonSerializable
{
    use JsonSerializableTrait;

    public function __construct(private string $id,private string $name, private string $email)
    {
    }

    /**
     * @param array<mixed> $body
     */
    public static function fromClientResponse(array $body): self
    {
        return new self($body['id'],$body['name'],$body['email']);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}
