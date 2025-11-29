<?php
declare(strict_types=1);

namespace Mollie\Shopware\Entity\Customer;

use Shopware\Core\Framework\Struct\ArrayStruct;

final class Customer extends ArrayStruct
{
    /**
     * @var string[]
     */
    private array $customerIds = [];
    private string $creditCardToken = '';
    private bool $shouldSaveCardDetail = false;

    /**
     * @param array<string> $customer_ids
     */
    public function __construct(array $customer_ids = [], string $credit_card_token = '', bool $shouldSaveCardDetail = false)
    {
        $this->customerIds = $customer_ids;
        $this->creditCardToken = $credit_card_token;
        $this->shouldSaveCardDetail = $shouldSaveCardDetail;
        parent::__construct([
            'customer_ids' => $this->customerIds,
            'credit_card_token' => $this->creditCardToken,
        ], 'mollie_customer');
    }

    /**
     * @return string[]
     */
    public function getCustomerIds(): array
    {
        return $this->customerIds;
    }

    public function getCreditCardToken(): string
    {
        return $this->creditCardToken;
    }

    public function isShouldSaveCardDetail(): bool
    {
        return $this->shouldSaveCardDetail;
    }
}
