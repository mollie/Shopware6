<?php

namespace Kiener\MolliePayments\Command\Refunds;

use Doctrine\DBAL\Connection;
use Kiener\MolliePayments\Service\ApplePayDirect\ApplePayDomainVerificationService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


class RefundCommand extends Command
{

    public static $defaultName = 'mollie:orders:refund';


    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var EntityRepositoryInterface
     */
    private $repoOrders;

    private Connection $connection;

    /**
     * @param LoggerInterface $logger
     * @param EntityRepositoryInterface $repoOrders
     * @param Connection $connection
     */
    public function __construct(LoggerInterface $logger, EntityRepositoryInterface $repoOrders, Connection $connection)
    {
        $this->logger = $logger;
        $this->repoOrders = $repoOrders;
        $this->connection = $connection;

        parent::__construct();
    }


    /**
     * @return void
     */
    protected function configure(): void
    {

        $this
            ->setName((string)self::$defaultName)
            ->setDescription('')
            ->addArgument('number', InputArgument::REQUIRED);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('MOLLIE Order Refund');


        $orderNumber = $input->getArgument('number');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderNumber', $orderNumber));
        $criteria->addAssociation('lineItems');


        $orders = $this->repoOrders->search($criteria, Context::createDefaultContext());

        /** @var OrderEntity $order */
        $order = $orders->first();


        foreach ($order->getLineItems() as $item) {

            if ($item->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }

            $productID = $item->getReferencedId();
            $quantity = $item->getQuantity();

            # TODO only do the FIRST time! otherwise stock would always increase!!!!
            $this->resetSaleStock($productID, $quantity);
        }


        return 1;
    }

    /**
     * @param string $productID
     * @param int $quantity
     * @return void
     * @throws \Doctrine\DBAL\Exception
     */
    private function resetSaleStock(string $productID, int $quantity): void
    {
        $update = new RetryableQuery(
            $this->connection,
            $this->connection->prepare(
                'UPDATE product SET available_stock = available_stock + :refundQuantity, sales = sales - :refundQuantity, updated_at = :now WHERE id = :id'
            )
        );

        $update->execute([
            'id' => Uuid::fromHexToBytes($productID),
            'refundQuantity' => $quantity,
            'now' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

}
