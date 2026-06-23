<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Installer;

use Mollie\Shopware\Component\Installer\DataRemoval\DataRemoverInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Orchestrates the removal of all Mollie-owned data. It only collects the tagged
 * {@see DataRemoverInterface} steps and runs each one; the actual logic lives in those steps.
 *
 * Each step is isolated in its own try/catch so a single failing step can never abort the
 * remaining ones nor make the plugin uninstall hard-fail.
 *
 * Public because it is fetched via container->get() in the plugin uninstall lifecycle.
 */
#[Autoconfigure(public: true)]
final class MollieDataRemover
{
    /**
     * @param iterable<DataRemoverInterface> $dataRemovers
     */
    public function __construct(
        #[AutowireIterator('mollie.data_remover')]
        private readonly iterable $dataRemovers,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
    }

    public function removeAllData(Context $context): void
    {
        $this->logger->info('Removing all Mollie data');

        foreach ($this->dataRemovers as $dataRemover) {
            try {
                $dataRemover->remove($context);
            } catch (\Throwable $exception) {
                $this->logger->error('A Mollie data removal step failed', [
                    'step' => get_class($dataRemover),
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
