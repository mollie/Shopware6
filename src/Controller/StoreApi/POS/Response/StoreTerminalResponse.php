<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\StoreApi\POS\Response;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class StoreTerminalResponse extends StoreApiResponse
{
    /**
     * @var ArrayStruct<mixed, mixed>
     */
    protected $object;

    public function __construct(bool $success)
    {
        $this->object = new ArrayStruct(
            [
                'success' => $success,
            ],
            'mollie_payments_pos_terminal_stored'
        );

        parent::__construct($this->object);
    }
}
