<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Cli;

use Mollie\Shopware\Component\Payment\ApplePayDirect\Service\ApplePayDomainVerificationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'mollie:applepay:download-verification',
    description: 'Download the latest Apple Pay Domain Verification File of Mollie.'
)]
final class ApplePayDownloadCommand extends Command
{
    public function __construct(
        private readonly ApplePayDomainVerificationService $domainVerification,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('MOLLIE Apple Pay Domain Verification file download');

        try {
            $this->logger->info('Downloading new Apple Pay Domain Verification file from CLI command');

            $downloaded = $this->domainVerification->downloadDomainAssociationFile();

            if (! $downloaded) {
                $this->logger->error('Apple Pay Domain Verification file could not be downloaded from Mollie');
                $io->error('The Apple Pay Domain Verification file could not be downloaded from Mollie.');

                return self::FAILURE;
            }

            $io->success('New Apple Pay Domain Verification file has been downloaded into your ./public/.well-known folder');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->logger->critical('Error when downloading Apple Pay Domain Verification file on CLI: ' . $exception->getMessage());

            $io->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
