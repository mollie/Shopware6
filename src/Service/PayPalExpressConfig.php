<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

class PayPalExpressConfig
{
    private int $enabled;
    private int $buttonStyle;
    private int $buttonShape;

    /**
     * @var string[]
     */
    private array $restrictions = [];

    public function __construct(
        ?int    $enabled = null,
        ?int    $buttonStyle = null,
        ?int    $buttonShape = null,
        ?string $restrictions = ''
    ) {
        $this->enabled = $enabled ?? 0;
        $this->buttonStyle = $buttonStyle ?? 1;
        $this->buttonShape = $buttonShape ?? 1;
        $restrictions = $restrictions ?? '';

        if ($restrictions !== '') {
            $this->restrictions = explode(' ', $restrictions);
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled === 1;
    }

    /**
     * @param array<mixed> $structData
     * @return array<mixed>
     */
    public function assign(array $structData): array
    {
        $structData['paypalExpressEnabled'] = $this->isEnabled();
        $structData['paypalExpressButtonStyle'] = $structData['paypalExpressButtonStyle'] ?? $this->buttonStyle;
        $structData['paypalExpressButtonShape'] = $structData['paypalExpressButtonShape'] ?? $this->buttonShape;
        $structData['paypalExpressRestrictions'] = array_unique(array_merge($structData['paypalExpressRestrictions'] ?? [], $this->restrictions));
        return $structData;
    }
}
