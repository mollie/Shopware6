<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Route;

use Mollie\Shopware\Component\Payment\ExpressMethod\AbstractCartBackupService;
use Mollie\Shopware\Component\Payment\ExpressMethod\CartBackupService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['store-api']])]
final class RestoreCartRoute extends AbstractRestoreCartRoute
{
    public function __construct(
        #[Autowire(service: CartBackupService::class)]
        private AbstractCartBackupService $cartBackupService,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): AbstractRestoreCartRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/mollie/applepay/restore-cart', name: 'store-api.mollie.apple-pay.restore-cart', methods: ['POST'])]
    public function restore(SalesChannelContext $salesChannelContext): RestoreCartResponse
    {
        $success = false;
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        try {
            $this->cartBackupService->restoreCart($salesChannelContext);
            $this->cartBackupService->clearBackup($salesChannelContext);
            $success = true;
            $this->logger->debug('Original cart was restored in apple pay direct',['salesChannelId' => $salesChannelId]);
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to restore cart in apple pay direct',['salesChannelId' => $salesChannelId]);
        }

        return new RestoreCartResponse($success);
    }
}
