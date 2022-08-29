<?php

namespace Kiener\MolliePayments\Controller\StoreApi\ApplePayDirect\Structs;

use Shopware\Core\Framework\Struct\Struct;

class SuccessStruct extends Struct
{
    /**
     * @var bool
     */
    protected $success;

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
        $this->success = $enabled;
        $this->apiAlias = $apiAlias;
    }

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @param bool $success
     */
    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }

    /**
     * @return string
     */
    public function getApiAlias(): string
    {
        return $this->apiAlias;
    }
}
