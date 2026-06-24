<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;

final class Terminal implements \JsonSerializable
{
    use JsonSerializableTrait;
    private string $serialNumber;

    public function __construct(private string $id,
        private string $description,
        private string $currency,
        private TerminalStatus $status,
        private TerminalBrand $brand,
        private TerminalModel $model
    ) {
    }

    /**
     * @param array<mixed> $body
     */
    public static function fromClientResponse(array $body): self
    {
        $terminal = new self($body['id'],
            $body['description'],
            $body['currency'],
            TerminalStatus::from($body['status']),
            TerminalBrand::from($body['brand']),
            TerminalModel::from($body['model']),
        );
        if ($body['serialNumber'] !== null) {
            $terminal->setSerialNumber($body['serialNumber']);
        }

        return $terminal;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setSerialNumber(string $serialNumber): void
    {
        $this->serialNumber = $serialNumber;
    }

    public function getSerialNumber(): string
    {
        return $this->serialNumber;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getStatus(): TerminalStatus
    {
        return $this->status;
    }

    public function getBrand(): TerminalBrand
    {
        return $this->brand;
    }

    public function getModel(): TerminalModel
    {
        return $this->model;
    }
}
