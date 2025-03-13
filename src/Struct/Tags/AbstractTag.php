<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Struct\Tags;

use Shopware\Core\Framework\Struct\Struct;

abstract class AbstractTag extends Struct
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $id;

    final private function __construct(string $name, string $id)
    {
        $this->name = $name;
        $this->id = $id;
    }

    abstract public static function create(): self;

    public function getName(): string
    {
        return $this->name;
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return static
     */
    protected static function createObject(string $name, string $id): self
    {
        return new static($name, $id);
    }
}
