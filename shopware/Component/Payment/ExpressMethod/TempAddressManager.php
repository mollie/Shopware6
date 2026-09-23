<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressMethod;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class TempAddressManager implements TempAddressManagerInterface
{
    /**
     * @param EntityRepository<CustomerAddressCollection<CustomerAddressEntity>> $customerAddressRepository
     */
    public function __construct(
        #[Autowire(service: ContextSwitchRoute::class)]
        private readonly AbstractContextSwitchRoute $contextSwitchRoute,
        #[Autowire(service: 'customer_address.repository')]
        private readonly EntityRepository $customerAddressRepository,
    ) {
    }

    public function apply(RequestDataBag $requestDataBag, TempAddress $tempAddress, SalesChannelContext $salesChannelContext): RequestDataBag
    {
        $tempAddressId = $tempAddress->getAddressId();

        $this->customerAddressRepository->upsert([$tempAddress->toUpsertArray()], $salesChannelContext->getContext());

        $requestDataBag->set(SalesChannelContextService::SHIPPING_ADDRESS_ID, $tempAddressId);
        $requestDataBag->set(SalesChannelContextService::BILLING_ADDRESS_ID, $tempAddressId);

        return $requestDataBag;
    }

    public function restore(SalesChannelContext $originalContext): void
    {
        $customer = $originalContext->getCustomer();
        if (! $customer instanceof CustomerEntity) {
            return;
        }

        $tempAddressId = TempAddress::getId($customer);
        $shippingAddressId = $this->getRestorableAddressId($customer->getActiveShippingAddress(), $customer->getDefaultShippingAddressId(), $tempAddressId);
        $billingAddressId = $this->getRestorableAddressId($customer->getActiveBillingAddress(), $customer->getDefaultBillingAddressId(), $tempAddressId);

        $requestDataBag = new RequestDataBag();
        $requestDataBag->set(SalesChannelContextService::SHIPPING_ADDRESS_ID, $shippingAddressId);
        $requestDataBag->set(SalesChannelContextService::BILLING_ADDRESS_ID, $billingAddressId);

        $this->contextSwitchRoute->switchContext($requestDataBag, $originalContext);

        $this->customerAddressRepository->delete([['id' => $tempAddressId]], $originalContext->getContext());
    }

    private function getRestorableAddressId(?CustomerAddressEntity $activeAddress, string $defaultAddressId, string $tempAddressId): string
    {
        if (! $activeAddress instanceof CustomerAddressEntity || $activeAddress->getId() === $tempAddressId) {
            return $defaultAddressId;
        }

        return $activeAddress->getId();
    }
}
