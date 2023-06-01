<?php

namespace Kiener\MolliePayments\Repository\CustomFieldSet;

use Shopware\Core\Framework\Context;

interface CustomFieldSetRepositoryInterface
{
    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return void
     */
    public function upsert(array $data, Context $context): void;
}
