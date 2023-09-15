<?php

namespace Kiener\MolliePayments\Command\DAL;

use Doctrine\DBAL\Connection;
use Kiener\MolliePayments\Repository\Product\ProductRepositoryInterface;
use Kiener\MolliePayments\Struct\Product\ProductAttributes;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DALCleanupCommand extends Command
{
    public static $defaultName = 'mollie:dal:cleanup';


    /**
     * @var ProductRepositoryInterface
     */
    private $repoProducts;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param ProductRepositoryInterface $repoProducts
     * @param Connection $connection
     * @param LoggerInterface $logger
     */
    public function __construct(ProductRepositoryInterface $repoProducts, Connection $connection, LoggerInterface $logger)
    {
        $this->repoProducts = $repoProducts;
        $this->connection = $connection;
        $this->logger = $logger;

        parent::__construct();
    }


    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName((string)self::$defaultName)
            ->setDescription('Cleaning and compressing unused Mollie data in your database. Please create a backup before');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Mollie DAL Data Cleanup');


        $answer = $io->ask('I have created a backup of the database (yes/no)');

        if ($answer !== 'yes') {
            $io->error('Please create a backup first');
            return 1;
        }

        $io->text('starting to clean unused Mollie data');


        try {
            $context = Context::createDefaultContext();

            $criteria = new Criteria();

            $products = $this->repoProducts->search($criteria, $context);


            /** @var ProductEntity $product */
            foreach ($products->getEntities() as $product) {
                $customFields = $product->getCustomFields();

                if ($customFields === null) {
                    continue;
                }

                $att = new ProductAttributes($product);

                $removableFields = $att->getRemovableFields();

                $io->section($product->getProductNumber() . ' ' . $product->getName());

                foreach ($removableFields as $field) {
                    $io->text('removing JSON key: ' . $field);

                    $productID = strtoupper($product->getId());

                    $sql = "UPDATE product_translation SET custom_fields = JSON_REMOVE(custom_fields, '$." . $field . "') WHERE product_id = 0x" . $productID;

                    $this->connection->executeStatement($sql);
                }
            }

            $io->success('Successfully cleaned and compressed Mollie data in your database');

            return 0;
        } catch (\Throwable $exception) {
            $this->logger->critical('Error when cleaning Mollie data in database: ' . $exception->getMessage());

            $io->error($exception->getMessage());

            return 1;
        }
    }
}
