<?php
declare(strict_types=1);

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

    public function __construct(bool $enabled, string $apiAlias)
    {
        $this->enabled = $enabled;
        $this->apiAlias = $apiAlias;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getApiAlias(): string
    {
        return $this->apiAlias;
    }
}
