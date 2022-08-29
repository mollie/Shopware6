<?php

namespace Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Structs;

use Shopware\Core\Framework\Struct\Struct;

class EnabledStruct extends Struct
{
    /**
     * @var bool
     */
    protected $enabled;

    /**
     * @var string
     */
    private $apiAlias;


    /**
     * @param bool $enabled
     * @param string $apiAlias
     */
    public function __construct(bool $enabled, string $apiAlias)
    {
        $this->enabled = $enabled;
        $this->apiAlias = $apiAlias;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param bool $enabled
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * @return string
     */
    public function getApiAlias(): string
    {
        return $this->apiAlias;
    }
}
