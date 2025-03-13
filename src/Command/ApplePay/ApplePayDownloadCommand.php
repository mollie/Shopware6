<?php

namespace Kiener\MolliePayments\Command\ApplePay;

use Kiener\MolliePayments\Components\ApplePayDirect\ApplePayDirect;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ApplePayDownloadCommand extends Command
{
    public static $defaultName = 'mollie:applepay:download-verification';


    /**
     * @var ApplePayDirect
     */
    private $applePay;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param ApplePayDirect $applePay
     * @param LoggerInterface $logger
     */
    public function __construct(ApplePayDirect $applePay, LoggerInterface $logger)
    {
        $this->applePay = $applePay;
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
            ->setDescription('Download the latest Apple Pay Domain Verification File of Mollie.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('MOLLIE Apple Pay Domain Verification file download');

        try {
            $this->logger->info('Downloading new Apple Pay Domain Verification file from CLI command');

            $this->applePay->downloadDomainAssociationFile();

            $io->success('New Apple Pay Domain Verification file has been downloaded into your ./public/.well-known folder');

            return 0;
        } catch (\Throwable $exception) {
            $this->logger->critical('Error when downloading Apple Pay Domain Verification file on CLI: ' . $exception->getMessage());

            $io->error($exception->getMessage());

            return 1;
        }
    }
}
