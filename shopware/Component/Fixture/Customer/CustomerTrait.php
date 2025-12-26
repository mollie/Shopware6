<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Fixture\Customer;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Salutation\SalutationCollection;

trait CustomerTrait
{
    private function getSalesChannelId(Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('type.name', 'Storefront'));
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->setLimit(1);

        /** @var EntityRepository<SalesChannelCollection<SalesChannelEntity>> $salesChannelRepository */
        $salesChannelRepository = $this->container->get('sales_channel.repository');
        $searchResult = $salesChannelRepository->searchIds($criteria, $context);

        return (string)$searchResult->firstId();
    }

    private function getDefaultSalutationId(Context $context): string
    {

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addSorting(new FieldSorting('salutationKey'));

        /** @var EntityRepository<SalutationCollection<SalesChannelEntity>> $salutationRepository */
        $salutationRepository = $this->container->get('salutation.repository');
        /** @var string $id */
        $id = $salutationRepository->searchIds($criteria, $context)->firstId();

        return $id;
    }

    private function getCountries(array $isoCodes, Context $context): EntitySearchResult
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('shippingAvailable', true))
            ->addFilter(new EqualsAnyFilter('iso', $isoCodes));

        /** @var EntityRepository<CountryCollection<CountryEntity>> $countryRepository */
        $countryRepository = $this->container->get('country.repository');
        return $countryRepository->search($criteria, $context);
    }

    private function getPaymentMethodId(Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->setLimit(1);
        /** @var EntityRepository<PaymentMethodCollection<PaymentMethodEntity>> $paymentMethodRepository */
        $paymentMethodRepository = $this->container->get('payment_method.repository');
        $searchResult = $paymentMethodRepository->searchIds($criteria, $context);
        return (string)$searchResult->firstId();
    }

    private function getAddresses(array $customer, Context $context): array
    {
        $countries = $this->getCountries(['DE', 'NL', 'FR', 'BE', 'PL', 'ES', 'SE'], $context);
        $addresses = [];
        /** @var CountryEntity $country */
        foreach ($countries as $country) {

            $addresses[$country->getIso()] = [
                'id' => $this->getAddressId($country->getIso()),
                'company' => 'Mollie B.V.',
                'firstName' => $customer['firstName'],
                'lastName' => $customer['lastName'],
                'street' => 'Cypress Street 1',
                'zipcode' => '10115',
                'city' => 'Berlin',
                'countryId' => $country->getId(),
                'phoneNumber' => '+490123456789',
            ];
        }

        return array_values($addresses);
    }

    private function getAddressId(string $iso): string
    {
        $addressId = sprintf('%s-%s', $this->getCustomerId(), $iso);
        return Uuid::fromStringToHex($addressId);
    }
}