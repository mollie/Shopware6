<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

interface SessionStorageInterface
{
    public function get(string $productId, ?string $customerId): ?string;

    public function set(string $productId, ?string $customerId, string $sessionId): void;

    public function remove(string $productId, ?string $customerId): void;
}
