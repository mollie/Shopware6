<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Mandate;

use Mollie\Shopware\Component\Mollie\MandateCollection;
use Mollie\Shopware\Component\Payment\Mandate\Route\ListMandatesResponse;
use Mollie\Shopware\Component\Payment\Mandate\Route\RevokeMandateResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListMandatesResponse::class)]
#[CoversClass(RevokeMandateResponse::class)]
final class MandateResponsesTest extends TestCase
{
    public function testListMandatesResponseStoresMandates(): void
    {
        $mandates = new MandateCollection();

        $response = new ListMandatesResponse($mandates);

        $this->assertSame($mandates, $response->getMandates());
    }

    public function testRevokeMandateResponseReturnsSuccessFlag(): void
    {
        $responseSuccess = new RevokeMandateResponse(true);
        $responseFailure = new RevokeMandateResponse(false);

        $this->assertTrue($responseSuccess->isSuccess());
        $this->assertFalse($responseFailure->isSuccess());
    }
}
