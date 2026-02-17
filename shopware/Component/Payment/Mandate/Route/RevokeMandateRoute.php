<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Mandate\Route;

use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Payment\Mandate\MandateException;
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
final class RevokeMandateRoute extends AbstractRevokeMandateRoute
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

    public function getDecorated(): AbstractRevokeMandateRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/mollie/mandate/revoke/{customerId}/{mandateId}', name: 'store-api.mollie.mandate.revoke', methods: ['POST'])]
    public function revoke(string $customerId, string $mandateId, SalesChannelContext $salesChannelContext): RevokeMandateResponse
    {
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $salesChannelName = (string) $salesChannelContext->getSalesChannel()->getName();
        $logData = [
            'path' => '/store-api/mollie/mandate/revoke/{customerId}/{mandateId}',
            'customerId' => $customerId,
            'salesChannelId' => $salesChannelId,
            'salesChannelName' => $salesChannelName,
        ];
        $this->logger->debug('Revoke mandate route called', $logData);

        $paymentSettings = $this->settings->getPaymentSettings($salesChannelId);

        if (! $paymentSettings->isOneClickPayment()) {
            $this->logger->debug('One click payment is disabled, mandates are not loaded', $logData);

            throw MandateException::oneClickPaymentDisabled($salesChannelId);
        }

        $customer = $salesChannelContext->getCustomer();
        if (! $customer instanceof CustomerEntity) {
            $this->logger->debug('Customer is not logged in', $logData);

            throw MandateException::customerNotLoggedIn();
        }
        $customerNumber = $customer->getCustomerNumber();
        $logData['customerNumber'] = $customerNumber;

        $customerExtension = $customer->getExtension(Mollie::EXTENSION);
        if (! $customerExtension instanceof Customer) {
            $this->logger->debug('Customer does not have mollie customer id', $logData);

            throw MandateException::mollieCustomerIdNotSet($customerNumber);
        }

        $apiSettings = $this->settings->getApiSettings($salesChannelId);
        $mollieProfileId = $apiSettings->getProfileId();
        $logData['profileId'] = $mollieProfileId;
        $mollieCustomerId = $customerExtension->getForProfileId($mollieProfileId,$apiSettings->getMode());
        if ($mollieCustomerId === null) {
            $this->logger->debug('Mollie Customer ID not found for Mollie Profile', $logData);

            throw MandateException::customerIdNotSetForProfile($customerNumber, $mollieProfileId);
        }

        $success = $this->mollieGateway->revokeMandate($mollieCustomerId, $mandateId, $salesChannelId);

        return new RevokeMandateResponse($success);
    }
}
