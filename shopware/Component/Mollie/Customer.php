<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;

final class Customer implements \JsonSerializable
{
    use JsonSerializableTrait;

    private Locale $locale;

    /**
     * @param array<mixed> $metaData
     */
    public function __construct(private string $id, private string $name, private string $email, private array $metaData)
    {
    }

    /**
     * @param array<mixed> $body
     */
    public static function fromClientResponse(array $body): self
    {
        $customer = new self(
            $body['id'],
            $body['name'],
            $body['email'],
            $body['metadata'],
        );
        if ($body['locale'] !== null) {
            $customer->setLocale(Locale::from($body['locale']));
        }

        return $customer;
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

    /**
     * @return mixed[]
     */
    public function getMetaData(): array
    {
        return $this->metaData;
    }

    public function getLocale(): Locale
    {
        return $this->locale;
    }

    public function setLocale(Locale $locale): void
    {
        $this->locale = $locale;
    }
}
