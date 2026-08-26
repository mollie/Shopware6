<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Support\Attachment\Generator;

use GuzzleHttp\Client;
use Mollie\Shopware\Component\Support\Attachment\Generator\PaymentMethodGenerator;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelRepository;
use Mollie\Shopware\Unit\Mollie\Fake\FakeClient;
use Mollie\Shopware\Unit\Mollie\Fake\FakeClientFactory;
use Mollie\Shopware\Unit\Payment\Fake\FakePaymentMethodRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * Almost every support case is "the method does not show up in my checkout". This attachment puts
 * the Shopware side and the Mollie side next to each other so the mismatch is visible at a glance.
 */
#[CoversClass(PaymentMethodGenerator::class)]
final class PaymentMethodGeneratorTest extends TestCase
{
    public function testEveryShopwareMethodIsListedWithItsState(): void
    {
        $content = $this->generate(shopwareMethods: [
            $this->shopwareMethod('Credit card', true),
            $this->shopwareMethod('iDEAL', false),
        ]);

        $this->assertStringContainsString('Credit card: Active', $content);
        $this->assertStringContainsString('iDEAL: Inactive', $content);
    }

    public function testTheMethodsMollieOffersAreListedPerSalesChannel(): void
    {
        $content = $this->generate(client: new FakeClient(body: [
            '_embedded' => ['methods' => [
                ['id' => 'ideal', 'description' => 'iDEAL', 'status' => 'activated'],
                ['id' => 'creditcard', 'description' => 'Credit card', 'status' => 'pending-review'],
            ]],
        ]));

        $this->assertStringContainsString('[ Storefront — Mollie Methods ]', $content);
        $this->assertStringContainsString('iDEAL: activated', $content);
        $this->assertStringContainsString('Credit card: pending-review', $content);
    }

    /**
     * An invalid API key is the most common cause, and the message has to say so instead of
     * leaving support with an empty list.
     */
    public function testAnUnreachableMollieIsReportedAsAPossibleKeyProblem(): void
    {
        // A client without a body answers 500 and raises a ClientException, like a rejected key.
        $content = $this->generate(client: new FakeClient());

        $this->assertStringContainsString('Could not get payment methods from Mollie, perhaps the API key is invalid', $content);
    }

    public function testAShopwareSideThatCannotBeReadIsReportedAsAnError(): void
    {
        $repository = new FakePaymentMethodRepository();
        $repository->withFindAllFailure(new \RuntimeException('database gone'));

        $content = $this->generator($repository)->generate(Context::createDefaultContext())->content;

        $this->assertStringContainsString('Error: database gone', $content);
    }

    public function testTheAttachmentIsSentAsAPlainTextFile(): void
    {
        $attachment = $this->generator()->generate(Context::createDefaultContext());

        $this->assertSame('payment_method_data.txt', $attachment->fileName);
        $this->assertSame('text/plain', $attachment->mimeType);
    }

    /**
     * @param list<PaymentMethodEntity> $shopwareMethods
     */
    private function generate(array $shopwareMethods = [], ?Client $client = null): string
    {
        $repository = new FakePaymentMethodRepository(methods: new PaymentMethodCollection($shopwareMethods));

        return $this->generator($repository, $client)->generate(Context::createDefaultContext())->content;
    }

    private function generator(?FakePaymentMethodRepository $repository = null, ?Client $client = null): PaymentMethodGenerator
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('sales-channel-1');
        $salesChannel->setName('Storefront');

        $salesChannelRepository = new FakeSalesChannelRepository();
        $salesChannelRepository->add($salesChannel);

        return new PaymentMethodGenerator(
            $salesChannelRepository,
            $repository ?? new FakePaymentMethodRepository(),
            new FakeClientFactory($client ?? new FakeClient(body: ['_embedded' => ['methods' => []]])),
            new FakeLogger()
        );
    }

    private function shopwareMethod(string $name, bool $active): PaymentMethodEntity
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('payment-method-' . $name);
        $paymentMethod->setName($name);
        $paymentMethod->setActive($active);

        return $paymentMethod;
    }
}
