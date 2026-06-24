<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Support\Attachment\Generator;

use Mollie\Shopware\Component\Mollie\Gateway\ClientFactoryInterface;
use Mollie\Shopware\Component\Payment\PaymentMethodRepository;
use Mollie\Shopware\Component\Payment\PaymentMethodRepositoryInterface;
use Mollie\Shopware\Component\Support\Attachment\Attachment;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PaymentMethodGenerator implements AttachmentGeneratorInterface
{
    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        #[Autowire(service: 'sales_channel.repository')]
        private readonly EntityRepository $salesChannelRepository,
        #[Autowire(service: PaymentMethodRepository::class)]
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly ClientFactoryInterface $clientFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function generate(Context $context): Attachment
    {
        $lines = [];

        $lines = array_merge($lines, $this->buildShopwarePaymentMethodLines($context));

        /** @var SalesChannelEntity $salesChannel */
        foreach ($this->getSalesChannels($context) as $salesChannel) {
            $lines = array_merge($lines, $this->buildMolliePaymentMethodLines($salesChannel));
        }

        $content = implode("\r\n", $lines);

        return new Attachment($content, 'payment_method_data.txt', 'text/plain');
    }

    /**
     * @return string[]
     */
    private function buildShopwarePaymentMethodLines(Context $context): array
    {
        $lines = [];
        $lines[] = '[ Payment Methods (Shopware) ]';

        try {
            $methods = $this->paymentMethodRepository->findAllMollieMethods($context);

            foreach ($methods as $method) {
                $lines[] = $method->getName() . ': ' . ($method->getActive() ? 'Active' : 'Inactive');
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to collect Shopware payment methods for support attachment: ' . $e->getMessage());
            $lines[] = 'Error: ' . $e->getMessage();
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @return string[]
     */
    private function buildMolliePaymentMethodLines(SalesChannelEntity $salesChannel): array
    {
        $lines = [];
        $lines[] = '[ ' . $salesChannel->getName() . ' — Mollie Methods ]';

        try {
            $client = $this->clientFactory->create($salesChannel->getId());
            $response = $client->get('methods');

            if ($response->getStatusCode() !== 200) {
                $lines[] = 'Could not fetch methods (HTTP ' . $response->getStatusCode() . ')';
            } else {
                $data = json_decode((string) $response->getBody(), true);
                $embedded = $data['_embedded']['methods'] ?? [];

                foreach ($embedded as $method) {
                    $lines[] = ($method['description'] ?? $method['id']) . ': ' . ($method['status'] ?? 'unknown');
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch Mollie payment methods for support attachment: ' . $e->getMessage());
            $lines[] = 'Could not get payment methods from Mollie, perhaps the API key is invalid';
        }

        $lines[] = '';

        return $lines;
    }

    private function getSalesChannels(Context $context): SalesChannelCollection
    {
        /** @var SalesChannelCollection */
        return $this->salesChannelRepository->search(new Criteria(), $context)->getEntities();
    }
}
