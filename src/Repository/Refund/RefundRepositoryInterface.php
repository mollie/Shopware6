<?php

namespace Kiener\MolliePayments\Repository\Refund;

use Shopware\Core\Framework\Context;

interface RefundRepositoryInterface
{
    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return void
     */
    public function upsert(array $data, Context $context): void;
    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return void
     */
    public function create(array $data, Context $context): void;
}
