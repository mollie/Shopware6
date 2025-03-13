<?php
declare(strict_types=1);

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

    public function __construct(bool $enabled, string $apiAlias)
    {
        $this->success = $enabled;
        $this->apiAlias = $apiAlias;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }

    public function getApiAlias(): string
    {
        return $this->apiAlias;
    }
}
