<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents\Route;

use Mollie\Shopware\Component\Payment\ExpressComponents\Route\CreateSessionResponse;
use Mollie\Shopware\Component\Payment\ExpressMethod\VisibilityRestriction;
use Mollie\Shopware\Component\Payment\ExpressMethod\VisibilityRestrictionCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateSessionResponse::class)]
final class CreateSessionResponseTest extends TestCase
{
    public function testStoreApiPayloadCarriesTheFieldNamesAHeadlessShopReads(): void
    {
        $restrictions = VisibilityRestrictionCollection::fromArray([VisibilityRestriction::CONFIRM->value]);

        $response = new CreateSessionResponse(true, $restrictions, 'ses-1', 'token-1');

        $this->assertSame(
            [
                'enabled' => true,
                'restrictions' => ['confirm'],
                'sessionId' => 'ses-1',
                'clientAccessToken' => 'token-1',
            ],
            $response->getObject()->all()
        );
        $this->assertSame('express_components_create_session_response', $response->getObject()->getApiAlias());
    }

    public function testAResponseWithoutASessionCarriesEmptyStringsInsteadOfNull(): void
    {
        $response = new CreateSessionResponse(false, new VisibilityRestrictionCollection());

        $this->assertSame(
            [
                'enabled' => false,
                'restrictions' => [],
                'sessionId' => '',
                'clientAccessToken' => '',
            ],
            $response->getObject()->all()
        );
    }
}
