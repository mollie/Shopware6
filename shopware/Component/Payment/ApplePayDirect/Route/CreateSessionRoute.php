<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Route;

use Mollie\Shopware\Component\Mollie\Gateway\ApplePayGateway;
use Mollie\Shopware\Component\Mollie\Gateway\ApplePayGatewayInterface;
use Mollie\Shopware\Component\Payment\ApplePayDirect\ApplePayDirectException;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

#[Route(defaults: ['_routeScope' => ['store-api']])]
final class CreateSessionRoute extends AbstractCreateSessionRoute
{
    public function __construct(
        #[Autowire(service: ApplePayGateway::class)]
        private ApplePayGatewayInterface $gateway,
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): AbstractCreateSessionRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/mollie/applepay/validate', name: 'store-api.mollie.apple-pay.validate', methods: ['POST'])]
    public function session(Request $request, SalesChannelContext $salesChannelContext): CreateSessionResponse
    {
        $validationUrl = $request->get('validationUrl');
        $domain = $request->get('domain', '');
        $salesChannel = $salesChannelContext->getSalesChannel();
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $logData = [
            'domain' => $domain,
            'validationUrl' => $validationUrl,
            'salesChannelId' => $salesChannelId,
        ];

        $this->logger->info('Apple pay direct session was requested', $logData);
        if ($validationUrl === null) {
            $this->logger->warning('Validation Url from apple pay JavaScript was not provided', $logData);
            throw ApplePayDirectException::validationUrlNotFound();
        }

        if (mb_strlen($domain) > 0) {
            $this->logger->debug('Custom domain was provided, validating', $logData);
            $applePaySettings = $this->settingsService->getApplePaySettings($salesChannelId);
            $allowedDomain = in_array($domain, $applePaySettings->getAllowDomainList());
            if (! $allowedDomain) {
                $logData['allowedDomains'] = $domain;
                $this->logger->error('Custom domain was provided but it is not in the allow list, please add the domain to allowed domain list in plugin configuration', $logData);
                throw new \InvalidArgumentException(sprintf('Creating session from domain %s is now allowed', $domain));
            }
        }
        $salesChannelDomains = $salesChannel->getDomains();
        if (mb_strlen($domain) === 0 && $salesChannelDomains instanceof SalesChannelDomainCollection) {
            $salesChannelDomain = $salesChannelDomains->get((string) $salesChannelContext->getDomainId());
            if ($salesChannelDomain instanceof SalesChannelDomainEntity) {
                $domain = $salesChannelDomain->getUrl();
            }
            $logData['domain'] = $domain;
            $this->logger->debug('Custom domain was not provided, using shopware storefront domain', $logData);
        }

        try {
            $session = $this->gateway->requestSession($domain, $validationUrl, $salesChannelContext->getSalesChannelId());
            $this->logger->info('Apple pay session successfully requested', $logData);
        } catch (\Throwable $exception) {
            $logData['exception'] = $exception->getMessage();
            $this->logger->error('Failed to request apple pay direct session', $logData);
            throw ApplePayDirectException::sessionRequestFailed($exception);
        }

        return new CreateSessionResponse($session);
    }
}
