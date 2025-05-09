<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Stock;

use Doctrine\DBAL\Connection;
use Kiener\MolliePayments\Components\RefundManager\Integrators\StockManagerInterface;
use Mollie\Api\Resources\Refund;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ContainerInterface;

class StockManager implements StockManagerInterface
{
    private const STOCK_MANAGER_PARAMETER_NAME = 'shopware.stock.enable_stock_management';
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var bool
     */
    private $enableStockManagement;

    public function __construct(Connection $connection, ContainerInterface $container)
    {
        $this->connection = $connection;

        /*
         * Enable stock management per default and disable it by config
         */
        $this->enableStockManagement = true;

        /*
         * We have to use here the container because the parameter does not exists in earlier shopware versions and we get an exceptions
         * when activating the plugin
         */
        if ($container->hasParameter(self::STOCK_MANAGER_PARAMETER_NAME)) {
            $this->enableStockManagement = (bool) $container->getParameter(self::STOCK_MANAGER_PARAMETER_NAME);
        }
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function increaseStock(OrderLineItemEntity $lineItem, int $quantity): void
    {
        if ($this->isEnabled() === false) {
            return;
        }

        if ($lineItem->getPayload() === null) {
            return;
        }

        // check if we have a product
        if (! isset($lineItem->getPayload()['productNumber'])) {
            return;
        }

        // extract our PRODUCT ID from the reference ID
        $productID = (string) $lineItem->getReferencedId();

        $update = $this->connection->prepare(
            'UPDATE product SET available_stock = available_stock + :quantity, sales = sales - :quantity, updated_at = :now WHERE id = :id'
        );
        $update->bindValue(':quantity', $quantity);
        $update->bindValue(':now', (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT));
        $update->bindValue(':id', Uuid::fromHexToBytes($productID));

        /** @phpstan-ignore-next-line  */
        if (method_exists($update, 'executeStatement')) {
            $update->executeStatement();

            return;
        }

        /** @phpstan-ignore-next-line  */
        if (method_exists($update, 'execute')) {
            $update->execute();
        }
    }

    /**
     * We do not listen to Feature::isActive('STOCK_HANDLING') this feature is disabled by default and enabled in later versions
     * if we listen to this feature, we will introduce breaking changes and this feature has to be enabled explicit so the refund manager will increase the stock
     */
    private function isEnabled(): bool
    {
        return $this->enableStockManagement;
    }
}
