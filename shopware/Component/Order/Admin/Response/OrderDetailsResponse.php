<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Order\Admin\Response;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;

final readonly class OrderDetailsResponse implements \JsonSerializable
{
    use JsonSerializableTrait;

    /**
     * @param null|array<string, mixed> $creditCard
     * @param null|array<string, mixed> $paypal
     * @param null|array<string, mixed> $bankTransfer
     * @param array<string, CancelStatusEntry> $cancelItem
     */
    public function __construct(
        public string $mollieId,
        public ?string $thirdPartyPaymentId,
        public ?array $creditCard,
        public ?array $paypal,
        public ?array $bankTransfer,
        public ?string $checkoutUrl,
        public bool $isSubscription,
        public ?string $subscriptionId,
        public bool $subscriptionEnabled,
        public RefundManagerConfig $refundManager,
        public ShippingData $shipping,
        public array $cancelItem,
        public bool $isMollieOrder = true,
    ) {
    }
}
