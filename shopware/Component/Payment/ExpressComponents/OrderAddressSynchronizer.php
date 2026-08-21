<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Writes the addresses of the express checkout onto an existing order.
 *
 * The shopper picks an address inside the wallet, and that one is the one that counts - it may
 * well differ from what was on the account before. The customer addresses are already updated by
 * the AddressSynchronizer, but an order keeps its own copies, so those have to follow or the shop
 * would ship to a different address than the one Mollie holds.
 *
 * New order address rows are written instead of the existing ones being changed, so the addresses
 * of the original attempt stay readable in the order history.
 */
final class OrderAddressSynchronizer implements OrderAddressSynchronizerInterface
{
    /**
     * @param EntityRepository<OrderCollection<OrderEntity>> $orderRepository
     */
    public function __construct(
        #[Autowire(service: 'order.repository')]
        private EntityRepository $orderRepository,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public function sync(OrderEntity $order, SalesChannelContext $salesChannelContext): void
    {
        $customer = $salesChannelContext->getCustomer();
        if (! $customer instanceof CustomerEntity) {
            return;
        }

        $billingAddress = $customer->getActiveBillingAddress() ?? $customer->getDefaultBillingAddress();
        $shippingAddress = $customer->getActiveShippingAddress() ?? $customer->getDefaultShippingAddress();

        if (! $billingAddress instanceof CustomerAddressEntity || ! $shippingAddress instanceof CustomerAddressEntity) {
            $this->logger->warning('Express components order addresses not synced, the customer has none', [
                'orderId' => $order->getId(),
                'customerId' => $customer->getId(),
            ]);

            return;
        }

        $billingAddressId = Uuid::randomHex();
        $shippingAddressId = $billingAddress->getId() === $shippingAddress->getId()
            ? $billingAddressId
            : Uuid::randomHex();

        $addresses = [$this->buildOrderAddress($billingAddressId, $billingAddress)];
        if ($shippingAddressId !== $billingAddressId) {
            $addresses[] = $this->buildOrderAddress($shippingAddressId, $shippingAddress);
        }

        $payload = [
            'id' => $order->getId(),
            'billingAddressId' => $billingAddressId,
            'addresses' => $addresses,
        ];

        $deliveries = [];
        foreach ($order->getDeliveries() ?? [] as $delivery) {
            $deliveries[] = [
                'id' => $delivery->getId(),
                'shippingOrderAddressId' => $shippingAddressId,
            ];
        }

        if ($deliveries !== []) {
            $payload['deliveries'] = $deliveries;
        }

        $context = $salesChannelContext->getContext();
        // billingAddressId is write protected for a sales channel context, the same way Shopware
        // itself writes a new order transaction from the store-api
        $context->scope(Context::SYSTEM_SCOPE, function () use ($payload, $context): void {
            $this->orderRepository->update([$payload], $context);
        });

        $this->logger->debug('Express components order addresses synced', [
            'orderId' => $order->getId(),
            'billingAddressId' => $billingAddressId,
            'shippingAddressId' => $shippingAddressId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOrderAddress(string $id, CustomerAddressEntity $address): array
    {
        return [
            'id' => $id,
            'salutationId' => $address->getSalutationId(),
            'firstName' => $address->getFirstName(),
            'lastName' => $address->getLastName(),
            'street' => $address->getStreet(),
            'zipcode' => $address->getZipcode(),
            'city' => $address->getCity(),
            'countryId' => $address->getCountryId(),
            'countryStateId' => $address->getCountryStateId(),
            'company' => $address->getCompany(),
            'department' => $address->getDepartment(),
            'title' => $address->getTitle(),
            'phoneNumber' => $address->getPhoneNumber(),
            'additionalAddressLine1' => $address->getAdditionalAddressLine1(),
            'additionalAddressLine2' => $address->getAdditionalAddressLine2(),
        ];
    }
}
