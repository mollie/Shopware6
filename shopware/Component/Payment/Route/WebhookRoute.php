<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Route;

use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class WebhookRoute extends AbstractWebhookRoute
{
    public function getDecorated(): self
    {
        throw new DecorationPatternException(self::class);
    }

    public function notify(Request $request, SalesChannelContext $context): WebhookRouteResponse
    {
        // TODO: Implement notify() method.
    }
}
