<?php
declare(strict_types=1);

namespace Mollie\Integration\Data;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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

    public function loginOrCreateAccount(string $email, SalesChannelContext $salesChannelContext, ?array $accountData = null): SalesChannelContext
    {
        $customerId = $this->findUserIdByEmail($email, $salesChannelContext->getContext());
        if ($customerId === null) {
            $this->createAccount($email, $salesChannelContext, $accountData);
            $customerId = $this->findUserIdByEmail($email, $salesChannelContext->getContext());
        }

        $options = [
            SalesChannelContextService::CUSTOMER_ID => $customerId,
        ];

        return $this->getSalesChannelContext($salesChannelContext->getSalesChannel(), $options);
    }

    private function createDefaultData(SalesChannelContext $salesChannelContext): array
    {
        $countryId = $this->getValidCountryId($salesChannelContext->getSalesChannelId());
        $salutationId = $this->getValidSalutationId();
        return [
            'salutationId' => $salutationId,
            'firstName' => 'Max',
            'lastName' => 'Mollie',
            'createCustomerAccount' => false,
            'password' => 'molliemollie',
            'billingAddress' => [
                'firstName' => 'Max',
                'lastName' => 'Mollie',
                'countryId' => $countryId,
                'street' => 'Mollie Street 123',
                'city' => 'Mollie City',
                'zipcode' => '12345',
            ]
        ];
    }
}
