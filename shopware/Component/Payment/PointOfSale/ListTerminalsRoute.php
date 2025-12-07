<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\PointOfSale;

use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
final class ListTerminalsRoute extends AbstractListTerminalsRoute
{
    public function __construct(
        #[Autowire(service: MollieGateway::class)]
        private MollieGatewayInterface $mollieGateway,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): AbstractListTerminalsRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/mollie/pos/terminals', name: 'store-api.mollie.pos.terminals', methods: ['GET'])]
    public function list(SalesChannelContext $salesChannelContext): ListTerminalsResponse
    {
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $salesChannelName = (string) $salesChannelContext->getSalesChannel()->getName();
        $logData = [
            'path' => '/store-api/mollie/pos/terminals',
            'salesChannelId' => $salesChannelId,
            'salesChannelName' => $salesChannelName,
        ];
        $this->logger->debug('List terminals route called', $logData);
        $terminals = $this->mollieGateway->listTerminals($salesChannelContext->getSalesChannelId());

        return new ListTerminalsResponse($terminals);
    }
}
