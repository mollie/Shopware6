<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Symfony\Component\HttpFoundation\Request;

/**
 * The urls Shopware sends the shopper to after the payment was finalized.
 *
 * Like in Shopware's own store-api they can be passed in, so a headless client points at its own
 * pages instead of storefront routes that do not exist there. The client cannot know the order id
 * when it calls the finish route, so it may leave a placeholder in its urls that is filled in
 * here. An empty url means the client passed none and the storefront pages are used instead.
 */
final class FinishUrls
{
    public const FINISH_URL_PARAMETER = 'finishUrl';
    public const ERROR_URL_PARAMETER = 'errorUrl';

    public const ORDER_ID_PLACEHOLDER = '{orderId}';

    public function __construct(
        private string $finishUrl,
        private string $errorUrl
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            (string) $request->get(self::FINISH_URL_PARAMETER, ''),
            (string) $request->get(self::ERROR_URL_PARAMETER, '')
        );
    }

    public function getFinishUrl(string $orderId): string
    {
        return str_replace(self::ORDER_ID_PLACEHOLDER, $orderId, $this->finishUrl);
    }

    public function getErrorUrl(string $orderId): string
    {
        return str_replace(self::ORDER_ID_PLACEHOLDER, $orderId, $this->errorUrl);
    }
}
