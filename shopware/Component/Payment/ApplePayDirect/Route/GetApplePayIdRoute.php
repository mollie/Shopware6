<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Route;

use Mollie\Shopware\Component\Payment\Method\ApplePayPayment;
use Mollie\Shopware\Component\Payment\PaymentMethodRepository;
use Mollie\Shopware\Component\Payment\PaymentMethodRepositoryInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['store-api']])]
final class GetApplePayIdRoute extends AbstractGetApplePayIdRoute
{
    public function __construct(
        #[Autowire(service: PaymentMethodRepository::class)]
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): AbstractGetApplePayIdRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(name: 'store-api.mollie.apple-pay.id', path: '/store-api/mollie/applepay/id', methods: ['GET'])]
    public function getId(SalesChannelContext $salesChannelContext): GetApplePayIdResponse
    {
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $applePayMethodId = $this->paymentMethodRepository->getIdByPaymentHandler(ApplePayPayment::class, $salesChannelId, $salesChannelContext->getContext());

        $this->logger->warning('Route get apple pay id route is deprecated, please use the enabled');

        return new GetApplePayIdResponse($applePayMethodId);
    }
}
