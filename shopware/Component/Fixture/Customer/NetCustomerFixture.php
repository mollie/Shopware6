<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Fixture\Customer;

use Mollie\Shopware\Component\Fixture\AbstractFixture;
use Mollie\Shopware\Component\Fixture\FixtureGroup;
use Psr\Container\ContainerInterface;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class NetCustomerFixture extends AbstractFixture
{
    use CustomerTrait;

    /**
     * @param EntityRepository<CustomerCollection<CustomerEntity>> $customerRepository
     */
    public function __construct(
        #[Autowire(service: 'customer.repository')]
        private readonly EntityRepository $customerRepository,
        #[Autowire(service: 'service_container')]
        private readonly ContainerInterface $container
    ) {

    }

    public function getGroup(): FixtureGroup
    {
        return FixtureGroup::DATA;
    }

    public function install(Context $context): void
    {
        $salutationId = $this->getDefaultSalutationId($context);
        $salesChannelId = $this->getSalesChannelId($context);
        $paymentMethodId = $this->getPaymentMethodId($context);
        $email = 'cypress-net@mollie.com';
        $password = 'cypress123';
        $defaultAddressId = $this->getAddressId('DE');

        $customer = [
            'id' => $this->getCustomerId(),
            'salutationId' => $salutationId,
            'accountType' => 'commercial',
            'customerNumber' => 'CYPRESS-' . date('YmdHis'),
            'firstName' => 'Cypress',
            'lastName' => 'TestUser',
            'createCustomerAccount' => true,
            'password' => $password,
            'email' => $email,
            'group' => [
                'id' => Uuid::fromStringToHex('net-customer-group'),
                'name' => 'Net customer group',
                'displayGross' => false
            ],
            'salesChannelId' => $salesChannelId,
            'defaultPaymentMethodId' => $paymentMethodId,
            'defaultBillingAddressId' => $defaultAddressId,
            'defaultShippingAddressId' => $defaultAddressId,
        ];

        $customer['addresses'] = $this->getAddresses($customer, $context);

        $this->customerRepository->upsert([$customer], $context);
    }

    public function uninstall(Context $context): void
    {
        $this->customerRepository->delete([[
            'id' => $this->getCustomerId(),
        ]], $context);
    }

    private function getCustomerId(): string
    {
        return Uuid::fromStringToHex('net-customer-id');
    }
}
