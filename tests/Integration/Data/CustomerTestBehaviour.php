<?php
declare(strict_types=1);

namespace Mollie\Integration\Data;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Controller\RegisterController;
use Symfony\Component\HttpFoundation\Response;

trait CustomerTestBehaviour
{
    use IntegrationTestBehaviour;
    use SalesChannelTestBehaviour;
    use RequestTestBehaviour;

    public function createAccount(string $email, SalesChannelContext $salesChannelContext, ?array $data = null): Response
    {
        if ($data === null) {
            $data = $this->createDefaultData($salesChannelContext);
            $data['email'] = $email;
        }

        $registerController = $this->getContainer()->get(RegisterController::class);

        $requestDat = new RequestDataBag($data);
        $request = $this->createStoreFrontRequest($salesChannelContext);
        $request->request->set('errorRoute', 'frontend.account.login.page');

        return $registerController->register($request, $requestDat, $salesChannelContext);
    }

    public function findUserIdByEmail(string $email, Context $context): ?string
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('customer.repository');
        $criteria = new Criteria();

        $criteria->addFilter(new EqualsFilter('email', $email));

        return $repository->searchIds($criteria, $context)->firstId();
    }

    public function loginOrCreateAccount(string $email, SalesChannelContext $salesChannelContext, ?array $accountData = null): string
    {
        $customerId = $this->findUserIdByEmail($email, $salesChannelContext->getContext());
        if ($customerId === null) {
            $this->createAccount($email, $salesChannelContext, $accountData);
            $customerId = $this->findUserIdByEmail($email, $salesChannelContext->getContext());
            $this->addAdditionalAddresses($customerId, $salesChannelContext);
        }

        return $customerId;
    }

    public function getCountryByIso(array $isoCodes, SalesChannelContext $salesChannelContext): IdSearchResult
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('country.repository');

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('shippingAvailable', true))
            ->addFilter(new EqualsAnyFilter('iso', $isoCodes))
        ;

        if ($salesChannelContext->getSalesChannelId() !== null) {
            $criteria->addFilter(new EqualsFilter('salesChannels.id', $salesChannelContext->getSalesChannelId()));
        }

        return $repository->searchIds($criteria, $salesChannelContext->getContext());
    }

    public function getUserAddressByIso(string $isoCode, SalesChannelContext $salesChannelContext): IdSearchResult
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('customer_address.repository');
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('country.iso', $isoCode))
            ->addFilter(new EqualsFilter('customerId', $salesChannelContext->getCustomer()->getId()))
        ;

        return $repository->searchIds($criteria, $salesChannelContext->getContext());
    }

    private function addAdditionalAddresses(string $customerId, SalesChannelContext $salesChannelContext): void
    {
        $countrySearchResult = $this->getCountryByIso(['NL', 'FR', 'BE', 'PL', 'ES', 'SE'], $salesChannelContext);
        $salutationId = $this->getValidSalutationId();
        $addresses = [];
        foreach ($countrySearchResult->getIds() as $countryId) {
            $addresses[] = [
                'salutationId' => $salutationId,
                'company' => 'Mollie Company',
                'firstName' => 'Max',
                'lastName' => 'Mollie',
                'countryId' => $countryId,
                'street' => 'Mollie Street 123',
                'city' => 'Mollie City',
                'zipcode' => '12345',
                'phoneNumber' => '+390123456789',
            ];
        }
        $customerData = [
            'id' => $customerId,
            'addresses' => $addresses,
        ];
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('customer.repository');
        $repository->upsert([
            $customerData
        ], $salesChannelContext->getContext());
    }

    private function createDefaultData(SalesChannelContext $salesChannelContext): array
    {
        $countryId = $this->getCountryByIso(['DE'], $salesChannelContext)->firstId();
        $salutationId = $this->getValidSalutationId();

        return [
            'salutationId' => $salutationId,
            'firstName' => 'Max',
            'lastName' => 'Mollie',
            'createCustomerAccount' => true,
            'password' => 'molliemollie',
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'billingAddress' => [
                'company' => 'Mollie Company',
                'firstName' => 'Max',
                'lastName' => 'Mollie',
                'countryId' => $countryId,
                'street' => 'Mollie Street 123',
                'city' => 'Mollie City',
                'zipcode' => '12345',
                'phoneNumber' => '+490123456789',
            ]
        ];
    }
}
