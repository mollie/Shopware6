<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Facade;

use Mollie\Api\Resources\OrderLine;

class MolliePaymentData
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $checkoutUrl;

    /**
     * @var OrderLine[]
     */
    private $mollieLineItems;

    /**
     * @var string
     */
    private $changeStatusUrl;

    /**
     * @param OrderLine[] $mollieLineItems
     */
    public function __construct(string $id, string $checkoutUrl, array $mollieLineItems, string $changeStatusUrl)
    {
        $this->id = $id;
        $this->checkoutUrl = $checkoutUrl;
        $this->mollieLineItems = $mollieLineItems;
        $this->changeStatusUrl = $changeStatusUrl;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCheckoutUrl(): string
    {
        return $this->checkoutUrl;
    }

    /**
     * @return OrderLine[]
     */
    public function getMollieLineItems(): array
    {
        return $this->mollieLineItems;
    }

    public function getChangeStatusUrl(): string
    {
        return $this->changeStatusUrl;
    }
}
