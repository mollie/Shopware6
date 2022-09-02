<?php

namespace Kiener\MolliePayments\Struct;

use Shopware\Core\Framework\Struct\Struct;

class ArrayStruct extends Struct
{
    /**
     * @var array<mixed>
     */
    protected $data;

    /**
     * @var string
     */
    private $apiAlias;


    /**
     * @param array<mixed> $data
     * @param string $apiAlias
     */
    public function __construct(array $data, string $apiAlias)
    {
        $this->data = $data;
        $this->apiAlias = $apiAlias;
    }

    /**
     * @return mixed[]
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param mixed[] $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    /**
     * @return string
     */
    public function getApiAlias(): string
    {
        return $this->apiAlias;
    }
}
