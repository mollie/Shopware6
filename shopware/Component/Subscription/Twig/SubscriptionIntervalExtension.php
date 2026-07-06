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
        $snippets = $this->snippetsForUnit($interval->getIntervalUnit());

        $snippetKey = $value === 1 ? $snippets['singular'] : $snippets['plural'];

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

    /**
     * @return array{singular: string, plural: string}
     */
    private function snippetsForUnit(IntervalUnit $unit): array
    {
        return match ($unit) {
            IntervalUnit::DAYS => [
                'singular' => 'molliePayments.subscriptions.options.everyDay',
                'plural' => 'molliePayments.subscriptions.options.everyDays',
            ],
            IntervalUnit::WEEKS => [
                'singular' => 'molliePayments.subscriptions.options.everyWeek',
                'plural' => 'molliePayments.subscriptions.options.everyWeeks',
            ],
            IntervalUnit::MONTHS => [
                'singular' => 'molliePayments.subscriptions.options.everyMonth',
                'plural' => 'molliePayments.subscriptions.options.everyMonths',
            ],
        };
    }
}
