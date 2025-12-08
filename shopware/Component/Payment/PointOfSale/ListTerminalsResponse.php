<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\PointOfSale;

use Mollie\Shopware\Component\Mollie\TerminalCollection;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct>
 */
final class ListTerminalsResponse extends StoreApiResponse
{
    public function __construct(private TerminalCollection $terminals)
    {
        $object = new ArrayStruct(
            [
                'terminals' => $this->terminals->jsonSerialize(),
            ],
            'mollie_payments_pos_terminals'
        );

        parent::__construct($object);
    }

    public function getTerminals(): TerminalCollection
    {
        return $this->terminals;
    }
}
