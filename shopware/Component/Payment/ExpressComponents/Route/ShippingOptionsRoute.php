<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents\Route;

use Mollie\Shopware\Component\Payment\ExpressComponents\ExpressComponentsException;
use Mollie\Shopware\Component\Payment\ExpressComponents\ShippingCallbackAddress;
use Mollie\Shopware\Component\Payment\ExpressComponents\ShippingOptionsResolver;
use Mollie\Shopware\Component\Payment\ExpressComponents\ShippingOptionsResolverInterface;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Called by Mollie whenever the shopper picks a different address inside the express
 * component, so the shipping options can be recalculated for that address.
 *
 * Mollie calls this server to server and cannot send credentials, so authentication is
 * disabled here the same way it is for the webhooks. The sales channel and the cart are not
 * derivable from the callback payload either - it only carries the session id - so both are
 * part of the url that was handed to Mollie.
 *
 * Request:  {"event": "shipping-address-changed", "sessionId": "sess_...", "shippingAddress": {"postalCode": "...", "city": "...", "region": "...", "country": "NL"}}
 * Response: {"options": [{"description": "...", "reference": "...", "amount": {"currency": "EUR", "value": "3.99"}}]}
 * Not deliverable: 422 {"type": "https://docs.mollie.com/reference/errors/unsupported-address", "status": 422, "detail": "..."}
 *
 * @see https://docs.mollie.com/docs/handle-shipping-options-with-express-component
 */
#[AsController]
#[Route(defaults: ['_routeScope' => ['api'], 'auth_required' => false, 'auth_enabled' => false])]
final class ShippingOptionsRoute extends AbstractShippingOptionsRoute
{
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        #[Autowire(service: ShippingOptionsResolver::class)]
        private ShippingOptionsResolverInterface $shippingOptionsResolver,
        #[Autowire(service: SalesChannelContextService::class)]
        private SalesChannelContextServiceInterface $salesChannelContextService,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public function getDecorated(): AbstractShippingOptionsRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/api/mollie/express-components/shipping-options/{salesChannelId}/{cartToken}', name: 'api.mollie.express-components.shipping-options', methods: ['GET', 'POST'])]
    public function shippingOptions(string $salesChannelId, string $cartToken, Request $request): ShippingOptionsResponse
    {
        $settings = $this->settingsService->getExpressComponentsSettings($salesChannelId);
        if ($settings->isEnabled() === false) {
            throw ExpressComponentsException::notEnabled($salesChannelId);
        }

        if ($cartToken === '') {
            throw ExpressComponentsException::cartTokenIsEmpty();
        }

        $body = json_decode((string) $request->getContent(), true);
        $body = is_array($body) ? $body : $request->query->all();

        $shippingAddress = $body['shippingAddress'] ?? [];
        $address = ShippingCallbackAddress::fromArray(is_array($shippingAddress) ? $shippingAddress : []);

        $this->logger->info('Express components shipping options requested', [
            'sessionId' => (string) ($body['sessionId'] ?? ''),
            'cartToken' => $cartToken,
            'requestParameter' => $body,
            'salesChannelId' => $salesChannelId,
        ]);

        $logData = [
            'sessionId' => (string) ($body['sessionId'] ?? ''),
            'cartToken' => $cartToken,
            'salesChannelId' => $salesChannelId,
        ];

        if ($address->getCountry() === '') {
            return $this->unsupported(ShippingOptionsResponse::ERROR_UNSUPPORTED_ADDRESS, 'The shipping address has no country', $logData);
        }

        $salesChannelContext = $this->salesChannelContextService->get(
            new SalesChannelContextServiceParameters($salesChannelId, $cartToken)
        );

        try {
            $shippingOptions = $this->shippingOptionsResolver->resolve($address, $salesChannelContext);
        } catch (ExpressComponentsException $exception) {
            if ($exception->getErrorCode() !== ExpressComponentsException::SHIPPING_COUNTRY_NOT_SUPPORTED) {
                throw $exception;
            }

            return $this->unsupported(ShippingOptionsResponse::ERROR_UNSUPPORTED_COUNTRY, $exception->getMessage(), $logData);
        }

        if ($shippingOptions->count() === 0) {
            return $this->unsupported(ShippingOptionsResponse::ERROR_UNSUPPORTED_ADDRESS, 'No shipping method is available for the shipping address', $logData);
        }

        $logData['responseParameter'] = ['options' => $shippingOptions->toArray()];
        $this->logger->info('Express components shipping options responded', $logData);

        return new ShippingOptionsResponse($shippingOptions);
    }

    /**
     * @param array<string, mixed> $logData
     */
    private function unsupported(string $type, string $detail, array $logData): ShippingOptionsResponse
    {
        $logData['type'] = $type;
        $logData['detail'] = $detail;
        $this->logger->warning('Express components shipping address is not deliverable', $logData);

        return ShippingOptionsResponse::unsupported($type, $detail);
    }
}
