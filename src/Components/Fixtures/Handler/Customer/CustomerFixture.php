<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Fixtures\Handler\Customer;

use Kiener\MolliePayments\Components\Fixtures\MollieFixtureHandlerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\Salutation\SalutationCollection;

class CustomerFixture implements MollieFixtureHandlerInterface
{
    private const CYPRESS_CUSTOMER_ID = '0d1eeedd6d22436385580e3ff42432c1';

    /**
     * @var EntityRepository<CustomerCollection>
     */
    private $repoCustomers;

    /**
     * @var EntityRepository<CustomerGroupCollection>
     */
    private $repoCustomerGroups;

    /**
     * @var EntityRepository<SalutationCollection>
     */
    private $repoSalutations;

    /**
     * @var EntityRepository<SalesChannelCollection>
     */
    private $repoSalesChannels;

    /**
     * @var EntityRepository<PaymentMethodCollection>
     */
    private $repoPaymentMethods;

    /**
     * @var EntityRepository<CountryCollection>
     */
    private $repoCountries;

    /**
     * @param EntityRepository<CustomerCollection> $repoCustomers
     * @param EntityRepository<CustomerGroupCollection> $repoCustomerGroups
     * @param EntityRepository<SalutationCollection> $repoSalutations
     * @param EntityRepository<SalesChannelCollection> $repoSalesChannels
     * @param EntityRepository<PaymentMethodCollection> $repoPaymentMethods
     * @param EntityRepository<CountryCollection> $repoCountries
     */
    public function __construct($repoCustomers, $repoCustomerGroups, $repoSalutations, $repoSalesChannels, $repoPaymentMethods, $repoCountries)
    {
        $this->repoCustomers = $repoCustomers;
        $this->repoCustomerGroups = $repoCustomerGroups;
        $this->repoSalutations = $repoSalutations;
        $this->repoSalesChannels = $repoSalesChannels;
        $this->repoPaymentMethods = $repoPaymentMethods;
        $this->repoCountries = $repoCountries;
    }

    public function install(): void
    {
        $context = Context::createDefaultContext();

        $email = 'cypress@mollie.com';
        $password = 'cypress123';

        $salesChannelId = $this->repoSalesChannels->searchIds((new Criteria())->setLimit(1), $context)->firstId();

        if (! $salesChannelId) {
            throw new \RuntimeException('No sales channel found.');
        }

        $addressId = Uuid::randomHex();

        $defaultPaymentMethodId = $this->repoPaymentMethods->searchIds((new Criteria())->setLimit(1), $context)->firstId();

        $firstSaluationId = $this->repoSalutations->searchIds((new Criteria())->setLimit(1), $context)->firstId();
        $firstCountryId = $this->repoCountries->searchIds((new Criteria())->setLimit(1), $context)->firstId();
        $firstCustomerGroupId = $this->repoCustomerGroups->searchIds((new Criteria())->setLimit(1), $context)->firstId();

        $payload = [[
            'id' => self::CYPRESS_CUSTOMER_ID,
            'salesChannelId' => $salesChannelId,
            'customerNumber' => 'CYPRESS-' . date('YmdHis'),
            'email' => $email,
            'password' => $password,
            'accountType' => 'commercial',
            'firstName' => 'Cypress',
            'lastName' => 'TestUser',
            'groupId' => $firstCustomerGroupId,
            'defaultPaymentMethodId' => $defaultPaymentMethodId,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [[
                'id' => $addressId,
                'salutationId' => $firstSaluationId,
                'company' => 'Mollie B.V.',
                'firstName' => 'Cypress',
                'lastName' => 'User',
                'street' => 'Cypress Street 1',
                'zipcode' => '10115',
                'city' => 'Berlin',
                'countryId' => $firstCountryId,
            ]],
        ]];

        $this->repoCustomers->upsert($payload, $context);
    }

    public function uninstall(): void
    {
        $context = Context::createDefaultContext();

        $this->repoCustomers->delete([
            [
                'id' => self::CYPRESS_CUSTOMER_ID
            ]
        ], $context);
    }
}
