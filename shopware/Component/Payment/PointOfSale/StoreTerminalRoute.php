<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\PointOfSale;

use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
final class StoreTerminalRoute
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/store-api/mollie/pos/store-terminal/{customerId}/{terminalId}', name: 'store-api.mollie.pos.store-terminal', methods: ['POST'])]
    public function storeTerminal(string $customerId, string $terminalId, SalesChannelContext $salesChannelContext): StoreTerminalResponse
    {
        $this->logger->warning('Deprecated URL was called, terminal IDs are not stored anymore, please provide "terminalId" in checkout');

        return new StoreTerminalResponse();
    }
}
