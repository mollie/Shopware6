<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Twig;

use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Entity\Product\Product;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class SubscriptionIntervalExtension extends AbstractExtension
{
    private const SNIPPET_PER_UNIT = [
        IntervalUnit::DAYS->value => [
            'singular' => 'molliePayments.subscriptions.options.everyDay',
            'plural' => 'molliePayments.subscriptions.options.everyDays',
        ],
        IntervalUnit::WEEKS->value => [
            'singular' => 'molliePayments.subscriptions.options.everyWeek',
            'plural' => 'molliePayments.subscriptions.options.everyWeeks',
        ],
        IntervalUnit::MONTHS->value => [
            'singular' => 'molliePayments.subscriptions.options.everyMonth',
            'plural' => 'molliePayments.subscriptions.options.everyMonths',
        ],
    ];

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('mollie_subscription_interval', $this->translateInterval(...)),
        ];
    }

    public function translateInterval(?Product $product): string
    {
        if ($product === null || ! $product->isSubscription()) {
            return '';
        }

        $interval = $product->getInterval();
        $value = $interval->getIntervalValue();
        $unit = $interval->getIntervalUnit()->value;

        if (! isset(self::SNIPPET_PER_UNIT[$unit])) {
            return '';
        }

        $snippetKey = $value === 1
            ? self::SNIPPET_PER_UNIT[$unit]['singular']
            : self::SNIPPET_PER_UNIT[$unit]['plural'];

        $text = $this->translator->trans($snippetKey, ['%value%' => $value]);

        $repetition = $product->getRepetition();
        if ($repetition >= 1) {
            $text .= ', ' . $this->translator->trans(
                'molliePayments.subscriptions.options.repetitionCount',
                ['%value%' => $repetition]
            );
        }

        return $text;
    }
}
