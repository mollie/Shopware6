<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\CreditCard;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['store-api']])]
final class StoreCreditCardTokenRoute
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/store-api/mollie/creditcard/store-token/{customerId}/{cardToken}', name: 'store-api.mollie.creditcard.store-token', methods: ['POST'])]
    public function store(string $customerId,string $cardToken, Context $context): StoreCreditCardTokenResponse
    {
        $this->logger->warning('Deprecated route was called, credit card tokens are not stored anymore, please provide "creditCardToken" in request body for payment');

        return new StoreCreditCardTokenResponse();
    }
}
