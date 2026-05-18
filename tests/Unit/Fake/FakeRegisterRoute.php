<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractRegisterRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\CustomerResponse;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeRegisterRoute extends AbstractRegisterRoute
{
    private bool $called = false;

    public function __construct(private readonly CustomerEntity $customer)
    {
    }

    public function getDecorated(): AbstractRegisterRoute
    {
        throw new \RuntimeException('not decorated');
    }

    public function register(RequestDataBag $data, SalesChannelContext $context, bool $validateStorefrontUrl = true, ?DataValidationDefinition $additionalValidationDefinitions = null): CustomerResponse
    {
        $this->called = true;

        return new CustomerResponse($this->customer);
    }

    public function wasRegistrationCalled(): bool
    {
        return $this->called;
    }
}
