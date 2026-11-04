<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents\Route;

use Mollie\Shopware\Component\Mollie\ShippingOptionCollection;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends StoreApiResponse<\Shopware\Core\Framework\Struct\ArrayStruct<array<string, mixed>>>
 */
final class ShippingOptionsResponse extends StoreApiResponse
{
    public const ERROR_UNSUPPORTED_ADDRESS = 'https://docs.mollie.com/reference/errors/unsupported-address';
    public const ERROR_UNSUPPORTED_COUNTRY = 'https://docs.mollie.com/reference/errors/unsupported-country';

    private const API_ALIAS = 'express_components_shipping_options_response';

    public function __construct(private ShippingOptionCollection $shippingOptions)
    {
        parent::__construct(self::struct([
            'options' => $shippingOptions->toArray(),
        ]));
    }

    /**
     * Mollie shows a localized error to the shopper based on the type, the detail is only logged.
     */
    public static function unsupported(string $type, string $detail): self
    {
        $response = new self(new ShippingOptionCollection());
        $response->object = self::struct([
            'type' => $type,
            'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
            'detail' => $detail,
        ]);
        $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);

        return $response;
    }

    public function getShippingOptions(): ShippingOptionCollection
    {
        return $this->shippingOptions;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return ArrayStruct<array<string, mixed>>
     */
    private static function struct(array $data): ArrayStruct
    {
        return new ArrayStruct($data, self::API_ALIAS);
    }
}
