<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie\Exception;

use Mollie\Shopware\Component\Mollie\Exception\ApiException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiException::class)]
final class ApiExceptionTest extends TestCase
{
    public function testExposesMollieErrorData(): void
    {
        $exception = new ApiException(422, 'Unprocessable Entity', 'The payment cannot be cancelled', 'no field');

        $this->assertSame(422, $exception->getCode());
        $this->assertSame('Unprocessable Entity', $exception->getTitle());
        $this->assertSame('The payment cannot be cancelled', $exception->getDetails());
        $this->assertSame('no field', $exception->getField());
    }

    #[DataProvider('cancellationDetailsProvider')]
    public function testDetectsCancellationNotPossible(string $details, bool $expected): void
    {
        $exception = new ApiException(422, 'Unprocessable Entity', $details, 'no field');

        $this->assertSame($expected, $exception->isCancellationNotPossible());
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function cancellationDetailsProvider(): array
    {
        return [
            'payment cannot be cancelled' => ['The payment cannot be cancelled', true],
            'order cannot be cancelled' => ['The order cannot be cancelled', true],
            'american spelling' => ['The payment cannot be canceled', true],
            'unrelated error' => ['The amount is invalid', false],
        ];
    }
}
