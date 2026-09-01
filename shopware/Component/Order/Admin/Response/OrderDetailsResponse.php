<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Order\Admin\Response;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;

final class OrderDetailsResponse implements \JsonSerializable
{
    use JsonSerializableTrait;

    /**
     * @param null|array<string, mixed> $creditCard
     * @param null|array<string, mixed> $paypal
     * @param null|array<string, mixed> $bankTransfer
     * @param array<string, CancelStatusEntry> $cancelItem
     */
    public function __construct(
        public readonly string $mollieId,
        public readonly ?string $thirdPartyPaymentId,
        public readonly ?array $creditCard,
        public readonly ?array $paypal,
        public readonly ?array $bankTransfer,
        public readonly ?string $checkoutUrl,
        public readonly bool $isSubscription,
        public readonly ?string $subscriptionId,
        public readonly bool $subscriptionEnabled,
        public readonly RefundManagerConfig $refundManager,
        public readonly ShippingData $shipping,
        public readonly array $cancelItem,
        public readonly bool $isMollieOrder = true,
    ) {
    }
}
