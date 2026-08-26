<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Mandate;

use Mollie\Shopware\Component\Payment\Mandate\MandateController;
use Mollie\Shopware\Component\Payment\Mandate\Route\StoreMandateIdRoute;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Payment\Mandate\Fake\FakeRevokeMandateRoute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Response;

/**
 * The credit card components in the storefront call these two endpoints directly, so both answer
 * with JSON the front end reads - never with a redirect or a rendered page.
 */
#[CoversClass(MandateController::class)]
final class MandateControllerTest extends TestCase
{
    private FakeSalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->salesChannelContext = new FakeSalesChannelContext('sc-1', 'token-1');
    }

    /**
     * Storing is a no-op since the mandate id travels through the checkout, but the endpoint must
     * keep answering successfully so older storefronts do not break.
     */
    public function testStoringAMandateIdStillAnswersSuccessfully(): void
    {
        $body = $this->body($this->controller(new FakeRevokeMandateRoute())->storeId('cust-1', 'mdt_1', $this->salesChannelContext));

        $this->assertTrue($body['success']);
        $this->assertSame('cust-1', $body['customerId']);
    }

    public function testRevokingAMandatePassesCustomerAndMandateToTheRoute(): void
    {
        $revokeRoute = new FakeRevokeMandateRoute();

        $this->controller($revokeRoute)->revoke('cust-1', 'mdt_1', $this->salesChannelContext);

        $this->assertSame([['customerId' => 'cust-1', 'mandateId' => 'mdt_1']], $revokeRoute->getRevoked());
    }

    public function testARevokedMandateIsReportedBackToTheStorefront(): void
    {
        $body = $this->body($this->controller(new FakeRevokeMandateRoute())->revoke('cust-1', 'mdt_1', $this->salesChannelContext));

        $this->assertTrue($body['success']);
        $this->assertSame('cust-1', $body['customerId']);
        $this->assertSame('mdt_1', $body['mandateId']);
    }

    /**
     * Mollie can refuse to revoke a mandate, e.g. when it is still used by a subscription. The
     * storefront then has to keep showing the card, so the failure must survive as success:false.
     */
    public function testAMandateMollieRefusesToRevokeIsReportedAsNotSuccessful(): void
    {
        $body = $this->body($this->controller(new FakeRevokeMandateRoute(success: false))->revoke('cust-1', 'mdt_1', $this->salesChannelContext));

        $this->assertFalse($body['success']);
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    private function controller(FakeRevokeMandateRoute $revokeRoute): MandateController
    {
        return new MandateController(new StoreMandateIdRoute(new NullLogger()), $revokeRoute);
    }
}
