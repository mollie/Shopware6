<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Struct;

use Shopware\Core\Framework\Struct\Struct;

class StringStruct extends Struct
{
    /**
     * @var string
     */
    protected $value;

    /**
     * @var string
     */
    private $apiAlias;


    /**
     * @param string $id
     * @param string $apiAlias
     */
    public function __construct(string $id, string $apiAlias)
    {
        $this->value = $id;
        $this->apiAlias = $apiAlias;
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @param string $value
     */
    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getApiAlias(): string
    {
        return $this->apiAlias;
    }
}
