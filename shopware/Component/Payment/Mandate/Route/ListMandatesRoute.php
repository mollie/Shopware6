<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Mandate\Route;

use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\MandateCollection;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Entity\Customer\Customer;
use Mollie\Shopware\Mollie;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['store-api']])]
final class ListMandatesRoute extends AbstractListMandatesRoute
{
    public function __construct(
        #[Autowire(service: MollieGateway::class)]
        private MollieGatewayInterface $mollieGateway,
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settings,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): AbstractListMandatesRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/mollie/mandates/{customerId}', name: 'store-api.mollie.mandates', methods: ['GET'])]
    public function list(string $customerId, SalesChannelContext $salesChannelContext): ListMandatesResponse
    {
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $salesChannelName = (string) $salesChannelContext->getSalesChannel()->getName();
        $logData = [
            'path' => '/store-api/mollie/mandates/{customerId}',
            'customerId' => $customerId,
            'salesChannelId' => $salesChannelId,
            'salesChannelName' => $salesChannelName,
        ];
        $this->logger->debug('List mandates route called', $logData);

        $mandateCollection = new MandateCollection();

        $customer = $salesChannelContext->getCustomer();
        if (! $customer instanceof CustomerEntity) {
            $this->logger->debug('Customer is not logged in', $logData);

            return new ListMandatesResponse($mandateCollection);
        }
        $logData['customerNumber'] = $customer->getCustomerNumber();

        $customerExtension = $customer->getExtension(Mollie::EXTENSION);
        if (! $customerExtension instanceof Customer) {
            $this->logger->debug('Customer does not have mollie customer id', $logData);

            return new ListMandatesResponse($mandateCollection);
        }

        $paymentSettings = $this->settings->getPaymentSettings($salesChannelId);

        if (! $paymentSettings->isOneClickPayment()) {
            $this->logger->debug('One click payment is disabled, mandates are not loaded', $logData);

            return new ListMandatesResponse($mandateCollection);
        }

        $apiSettings = $this->settings->getApiSettings($salesChannelId);
        $mollieProfileId = $apiSettings->getProfileId();
        $logData['profileId'] = $mollieProfileId;
        $mollieCustomerId = $customerExtension->getForProfileId($mollieProfileId);
        if ($mollieCustomerId === null) {
            $this->logger->debug('Mollie Customer ID not found for Mollie Profile', $logData);

            return new ListMandatesResponse($mandateCollection);
        }

        $mandateCollection = $this->mollieGateway->listMandates($mollieCustomerId, $salesChannelId);
        $logData['total'] = $mandateCollection->count();
        $this->logger->info('Mandates for mollie customer loaded', $logData);

        return new ListMandatesResponse($mandateCollection);
    }
}
