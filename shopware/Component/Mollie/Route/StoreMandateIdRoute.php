<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Route;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
final class StoreMandateIdRoute
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/store-api/mollie/creditcard/store-mandate-id/{customerId}/{mandateId}', name: 'store-api.mollie.creditcard.store-mandate-id', methods: ['POST'])]
    public function store(string $customerId, string $mandateId, Context $context): StoreMandateIdResponse
    {
        $this->logger->warning('Deprecated URL was called, mandate ID are not stored anymore, please provide "mandateId" in checkout');

        return new StoreMandateIdResponse();
    }
}
