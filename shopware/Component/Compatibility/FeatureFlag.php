<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Compatibility;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Feature;

/**
 * Always use this instead of Feature::isActive().
 *
 * Feature::isActive() emits an "Unknown feature" E_USER_WARNING when the flag registry is already
 * populated but does not contain the flag, and APP_ENV is not prod. APP_DEBUG=1 turns that warning
 * into an exception. If it happens while a class is being reflected, the class cannot be read and
 * every attribute on it - including #[AutoconfigureTag] - is silently dropped.
 *
 * That window is real for this plugin: services.xml registers the whole `shopware/` directory as a
 * prototype, so Symfony reflects every class in it while the bundle extension loads, long before
 * Shopware's FeatureFlagCompilerPass registers the flags. On its own the registry is still empty
 * there and isActive() stays silent, but any plugin booting earlier and registering its own flags
 * leaves it non-empty and incomplete - which is exactly how issue #1440 lost all payment handlers.
 *
 * Feature::has() alone would not do, because it is a plain isset() on that same registry: at include
 * time it reports false even for a flag the shop has enabled, and we would silently take the wrong
 * branch. So when the registry cannot answer, we read the environment ourselves.
 *
 * @see https://github.com/mollie/Shopware6/issues/1440
 */
final class FeatureFlag
{
    private const FEATURE_ALL_MAJOR = 'major';

    public static function isActive(string $flag): bool
    {
        if (Feature::has($flag)) {
            return Feature::isActive($flag);
        }

        return self::isActiveInEnvironment($flag);
    }

    /**
     * Mirrors the environment part of Feature::isActive(). This stays authoritative for major flags
     * such as v6.7.0.0: feature.yaml marks them `toggleable: false` and FeatureFlagRegistry filters
     * them out of the database-stored flags, so the environment is the only place to switch them on.
     *
     * Plain FEATURE_ALL only enables minor flags in Shopware, majors need FEATURE_ALL=major.
     */
    private static function isActiveInEnvironment(string $flag): bool
    {
        $name = Feature::normalizeName($flag);

        foreach ([$name, strtolower($name)] as $variable) {
            if (EnvironmentHelper::hasVariable($variable)) {
                $value = trim((string) EnvironmentHelper::getVariable($variable));

                return $value !== '' && $value !== '0' && $value !== 'false';
            }
        }

        return EnvironmentHelper::getVariable('FEATURE_ALL', '') === self::FEATURE_ALL_MAJOR;
    }
}
